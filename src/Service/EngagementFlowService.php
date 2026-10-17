<?php

namespace App\Service;

use App\Entity\StudentProfile;
use App\Enum\BarrierType;
use App\Enum\EngagementTrigger;
use App\Enum\FlowStep;
use App\Enum\Hatirlatma72SaatResponse;
use App\Enum\KapanisKodu;
use App\Enum\MotivasyonResponse;
use App\Enum\Persona;
use App\Enum\Phase;
use App\Enum\PreferredStudyTime;
use App\Enum\StudentState;
use App\Enum\T1Response;
use App\Enum\WeeklyHoursAvailable;
use App\Enum\WhatsAppTemplate;
use App\Enum\ZamanDonusSuresi;
use App\Enum\ZamanResponse;
use App\Enum\ZorlukResponse;

/**
 * M1 (engel teşhisi) senaryosunun karar ve mutasyon mantığı: hangi tetikleyicide M1
 * gönderilir, bir yanıt geldiğinde `StudentProfile` nasıl güncellenir ve sıradaki mesaj
 * hangisidir. Taşıma katmanından (WhatsApp gönderimi/webhook yanıt yönlendirmesi) bilinçli
 * olarak bağımsızdır — bkz. proje notları: telefon numarası <-> öğrenci eşlemesi şemada
 * henüz yok, bu yüzden gerçek gönderim/webhook yönlendirmesi bu tez turunun kapsamı dışında
 * bırakıldı. Bu servis yalnızca "student X şu yanıtı verdi" bilgisinden hareketle doğru
 * mutasyonu ve bir sonraki mesajı üretir; entity'leri flush ETMEZ (çağıran taraf yapar).
 *
 * Her metot artık bir {@see Persona} parametresi alır (kullanıcı kararı 2026-10-04: "tonlama
 * benim için önemli") — mesaj metni {@see EngagementFlowMessages}'ten persona'ya göre seçilir.
 * Dönen {@see FlowOutcome::$template} hâlâ persona'dan BAĞIMSIZ "mantıksal nokta" kimliğidir
 * ({@see WhatsAppTemplate}); gerçek Meta şablon adını (persona sonekli) almak için çağıran taraf
 * {@see WhatsAppTemplate::metaAdiPersonaIcin()}'i kendi elindeki persona ile çağırmalı.
 *
 * M1 yalnızca Normal dönem fazında kullanılır — Sınav Öncesi/Sınav Günü fazlarında
 * T2/T3/T4'ün kendi sınav-özel (barrier sorusu içermeyen) varyantları kullanılır; bu
 * varyantların metinleri elimizde değil, bu yüzden burada üretilmiyor (bkz. proje notları).
 */
class EngagementFlowService
{
    /** last_barrier_type kaç hafta geçerli sayılır (kullanıcı tarafından netleştirilmedi — varsayılan). */
    public const int BARRIER_GECERLILIK_HAFTASI = 4;

    /**
     * "Şimdilik mesaj göndermeyin" seçildiğinde `silence_until` kaç gün ileriye ayarlanır
     * (2026-10-16, kullanıcı kararı: artık otomatik geri dönüş YOK — eskiden 180 gündü,
     * şimdi fiilen süresiz sayılacak kadar uzun bir değer). Geri dönüş otomatik değil,
     * öğrencinin kendi isteğiyle (bkz. `WhatsAppWebhookController::handleKararAgaciYaniti()`'teki
     * "Merhaba" ile yeniden etkinleştirme dalı) oluyor.
     */
    private const int SESSIZLIK_SURESIZ_GUN = 36500;

    /**
     * M1, yalnızca T2/T3/T4 tetiklendiğinde, Normal fazdayken, öğrencinin geçerli bir
     * last_barrier_type'ı yokken VE zaten bekleyen bir senaryosu yokken gönderilir.
     */
    public function shouldSendM1(
        ?EngagementTrigger $trigger,
        StudentProfile $profile,
        ?Phase $aktifFaz,
        \DateTimeImmutable $today,
    ): bool {
        if (!in_array($trigger, [EngagementTrigger::T2, EngagementTrigger::T3, EngagementTrigger::T4], true)) {
            return false;
        }

        if (null !== $aktifFaz && Phase::Normal !== $aktifFaz) {
            return false;
        }

        if (null !== $profile->getPendingFlowStep()) {
            return false;
        }

        return !$this->isBarrierTypeValid($profile, $today);
    }

    /**
     * `student_profile.last_barrier_type` bu "dönem/geçerlilik penceresi" içinde dolu mu?
     * (bkz. {@see self::BARRIER_GECERLILIK_HAFTASI}). Tek bir yüklü entity için — toplu
     * (53k öğrenci) tarama için {@see \App\Repository\StudentProfileRepository::findStudentIdsWithValidBarrierType()}.
     */
    public function isBarrierTypeValid(StudentProfile $profile, \DateTimeImmutable $today): bool
    {
        $setAt = $profile->getLastBarrierTypeSetAt();
        if (null === $profile->getLastBarrierType() || null === $setAt) {
            return false;
        }

        return $setAt >= $today->modify(sprintf('-%d weeks', self::BARRIER_GECERLILIK_HAFTASI));
    }

    /**
     * M1 açılışı artık tetikleyiciye göre 3 ayrı şablona ({@see WhatsAppTemplate::M1AcilisT2}/
     * `T3`/`T4`) bağlanıyor — `templates.docx`'in son hali kararı, bkz. {@see EngagementFlowMessages::m1()}.
     */
    public function baslatM1(StudentProfile $profile, string $ad, EngagementTrigger $trigger, Persona $persona): FlowOutcome
    {
        $profile->setPendingFlowStep(FlowStep::EngelBekleniyor);

        return new FlowOutcome(EngagementFlowMessages::m1($ad, $trigger, $persona), FlowStep::EngelBekleniyor, self::m1Sablonu($trigger));
    }

    /**
     * M1 açılışının tetikleyiciye göre hangi şablona ({@see WhatsAppTemplate::M1AcilisT2}/`T3`/`T4`)
     * gideceği — SAF (mutasyon yapmaz). {@see self::baslatM1()} bunu kullanır; gönderimin
     * hemen değil GERÇEKTEN gönderildiğinde (bkz. {@see \App\Entity\ScheduledMessage}) uygulanması
     * gerektiği durumlarda (telefonu olan öğrenciler) çağıran taraf `pending_flow_step`
     * mutasyonunu ERTELEYİP yalnızca bu metotla şablonu/metni hesaplamak isteyebilir — bkz.
     * {@see \App\Command\EvaluateDailyTriggersCommand}.
     */
    public static function m1Sablonu(EngagementTrigger $trigger): WhatsAppTemplate
    {
        return match ($trigger) {
            EngagementTrigger::T2 => WhatsAppTemplate::M1AcilisT2,
            EngagementTrigger::T3 => WhatsAppTemplate::M1AcilisT3,
            EngagementTrigger::T4 => WhatsAppTemplate::M1AcilisT4,
            EngagementTrigger::T1 => throw new \LogicException('M1, T1 tetikleyicisinden asla başlatılmaz.'),
        };
    }

    public function barrierYanitiIsle(StudentProfile $profile, BarrierType $yanit, Persona $persona): FlowOutcome
    {
        $profile->setLastBarrierType($yanit);

        $sonuc = match ($yanit) {
            BarrierType::Zaman => new FlowOutcome(EngagementFlowMessages::zamanTakip($persona), FlowStep::ZamanTakibiBekleniyor, WhatsAppTemplate::ZamanTakip),
            BarrierType::Zorluk => new FlowOutcome(EngagementFlowMessages::zorlukTakip($persona), FlowStep::ZorlukTakibiBekleniyor, WhatsAppTemplate::ZorlukTakip),
            BarrierType::Motivasyon => new FlowOutcome(
                EngagementFlowMessages::motivasyonTakip($profile->getLearningGoal(), $persona),
                FlowStep::MotivasyonTakibiBekleniyor,
                WhatsAppTemplate::MotivasyonTakip,
                [EngagementFlowMessages::motivasyonHedefMetni($profile->getLearningGoal(), $persona)],
            ),
        };

        // Bug düzeltmesi (2026-10-04, simülasyon komutuyla ortaya çıktı): bu metot
        // `FlowOutcome::$nextStep`'i dolduruyordu ama `pending_flow_step`'i entity üzerinde HİÇ
        // set etmiyordu — diğer tüm ...YanitiIsle() metotları bunu yapar, burada unutulmuştu.
        $profile->setPendingFlowStep($sonuc->nextStep);

        return $sonuc;
    }

    // --- T1 yanıtı (templates.docx Bölüm 1.1/1.2) --------------------------------------

    public function t1YanitiIsle(StudentProfile $profile, T1Response $yanit, Persona $persona): FlowOutcome
    {
        $profile->setPendingFlowStep(null);

        return match ($yanit) {
            T1Response::SimdiBasla => new FlowOutcome(EngagementFlowMessages::t1SimdiBaslaKapanis($persona), null, WhatsAppTemplate::T1SimdiBaslaKapanis),
            T1Response::DahaSonra => new FlowOutcome(EngagementFlowMessages::t1DahaSonraKapanis($persona), null, WhatsAppTemplate::T1DahaSonraKapanis),
        };
    }

    // --- Dal 1: Zaman -----------------------------------------------------------------

    public function zamanYanitiIsle(StudentProfile $profile, ZamanResponse $yanit, Persona $persona): FlowOutcome
    {
        $profile->setTimeBarrierChoice($yanit);

        return match ($yanit) {
            ZamanResponse::HaftadaBirKisaOturum => $this->zamanHaftadaBirSecildi($profile, $persona),
            ZamanResponse::TempoAyniKalsin => $this->zamanTempoAyniKalsinSecildi($profile, $persona),
            ZamanResponse::AraVermekIstiyorum => $this->zamanAraVermeSecildi($profile, $persona),
        };
    }

    private function zamanHaftadaBirSecildi(StudentProfile $profile, Persona $persona): FlowOutcome
    {
        $profile->setWeeklyHoursAvailable(WeeklyHoursAvailable::BirGun);
        $profile->setPendingFlowStep(FlowStep::ZamanGunSaatiBekleniyor);

        return new FlowOutcome(EngagementFlowMessages::zamanSaatSorusu($persona), FlowStep::ZamanGunSaatiBekleniyor, WhatsAppTemplate::ZamanSaatSorusu);
    }

    private function zamanTempoAyniKalsinSecildi(StudentProfile $profile, Persona $persona): FlowOutcome
    {
        $profile->setPendingFlowStep(null);

        return new FlowOutcome(EngagementFlowMessages::zamanTempoKapanis($persona), null, WhatsAppTemplate::ZamanTempoKapanis);
    }

    private function zamanAraVermeSecildi(StudentProfile $profile, Persona $persona): FlowOutcome
    {
        $profile->setPendingFlowStep(FlowStep::ZamanDonusSuresiBekleniyor);

        return new FlowOutcome(EngagementFlowMessages::zamanDonusSorusu($persona), FlowStep::ZamanDonusSuresiBekleniyor, WhatsAppTemplate::ZamanDonusSorusu);
    }

    /**
     * Kaba kategoriyi ({@see PreferredStudyTime}) hem kendi alanına, hem de ({@see
     * PreferredStudyTime::temsilciSaat()} üzerinden) {@see MessageTimePriorityResolver}'ın
     * kullandığı kesin saat alanına ({@see StudentProfile::setPreferredMessageTime()}) yazar —
     * kullanıcı kararı: ayrı bir "kesin saat" sorusu yerine mevcut 3 seçenekli soru temsilci
     * saate eşlenir (bkz. proje notları, bu akış şu an yalnızca "zaman" engeli + "haftada 1 kısa
     * oturum" dalında tetikleniyor).
     */
    public function zamanSaatYanitiIsle(StudentProfile $profile, PreferredStudyTime $saat, Persona $persona): FlowOutcome
    {
        $profile->setPreferredStudyTime($saat);
        $profile->setPreferredMessageTime($saat->temsilciSaat());
        $profile->setPendingFlowStep(null);

        return new FlowOutcome(EngagementFlowMessages::zamanSaatKapanis($persona), null, WhatsAppTemplate::ZamanSaatKapanis);
    }

    public function zamanDonusSuresiYanitiIsle(StudentProfile $profile, ZamanDonusSuresi $yanit, \DateTimeImmutable $now, Persona $persona): FlowOutcome
    {
        $gun = match ($yanit) {
            ZamanDonusSuresi::BirHafta => 7,
            ZamanDonusSuresi::IkiHafta => 14,
            ZamanDonusSuresi::MesajGonderilmesin => self::SESSIZLIK_SURESIZ_GUN,
        };

        $profile->setSilenceUntil($now->modify(sprintf('+%d days', $gun)));
        $profile->setCurrentState(StudentState::AraVermis);
        $profile->setPendingFlowStep(null);

        return new FlowOutcome(EngagementFlowMessages::zamanDonusKapanis($persona), null, WhatsAppTemplate::ZamanDonusKapanis);
    }

    // --- Dal 2: Zorluk (eğitmen/destek yönlendirmesi YOK; tek açık uçlu istisna: "notumu bırak") ---

    public function zorlukYanitiIsle(StudentProfile $profile, ZorlukResponse $yanit, Persona $persona): FlowOutcome
    {
        $profile->setDifficultyBarrierChoice($yanit);

        if (ZorlukResponse::NotumuBirak === $yanit) {
            $profile->setPendingFlowStep(FlowStep::ZorlukNotBekleniyor);

            return new FlowOutcome(EngagementFlowMessages::zorlukNotSorusu($persona), FlowStep::ZorlukNotBekleniyor, WhatsAppTemplate::ZorlukNotSorusu);
        }

        $profile->setPendingFlowStep(null);

        return new FlowOutcome(EngagementFlowMessages::zorlukMateryalKapanis($persona), null, WhatsAppTemplate::ZorlukMateryalKapanis);
    }

    /**
     * "Notumu bırak, sonra değerlendirilsin" sonrası öğrencinin serbest metin açıklamasını işler.
     */
    public function zorlukNotYanitiIsle(StudentProfile $profile, string $notMetni, Persona $persona): FlowOutcome
    {
        $profile->setDifficultyNoteText($notMetni);
        $profile->setPendingFlowStep(null);

        return new FlowOutcome(EngagementFlowMessages::zorlukNotKapanis($persona), null, WhatsAppTemplate::ZorlukNotKapanis);
    }

    // --- Dal 3: Motivasyon --------------------------------------------------------------

    public function motivasyonYanitiIsle(StudentProfile $profile, MotivasyonResponse $yanit, Persona $persona): FlowOutcome
    {
        $profile->setMotivationBarrierChoice($yanit);

        return match ($yanit) {
            MotivasyonResponse::HedefHalaGecerli => $this->motivasyonHedefGecerliSecildi($profile, $persona),
            MotivasyonResponse::EminDegil => $this->motivasyonEminDegilSecildi($profile, $persona),
        };
    }

    /**
     * Doküman: "İlerleme özeti hedefe atıfla sunulur, tempo/ara verme tercihi sorulur" — ikinci
     * kısım Dal 1'in (Zaman) M2 sorusuyla AYNI, bu yüzden aynı adıma ({@see FlowStep::ZamanTakibiBekleniyor})
     * yönlendirilir; yanıtı da {@see self::zamanYanitiIsle()} işler.
     */
    private function motivasyonHedefGecerliSecildi(StudentProfile $profile, Persona $persona): FlowOutcome
    {
        $profile->setPendingFlowStep(FlowStep::ZamanTakibiBekleniyor);

        return new FlowOutcome(EngagementFlowMessages::motivasyonGecerliTakip($persona), FlowStep::ZamanTakibiBekleniyor, WhatsAppTemplate::MotivasyonGecerliTakip);
    }

    private function motivasyonEminDegilSecildi(StudentProfile $profile, Persona $persona): FlowOutcome
    {
        $profile->setPendingFlowStep(null);

        return new FlowOutcome(EngagementFlowMessages::motivasyonEminDegilKapanis($persona), null, WhatsAppTemplate::MotivasyonEminDegilKapanis);
    }

    // --- Bölüm 3: T2/T3 Sınav Öncesi + T4 kendi seçenek yanıtı (K1/K2/K3 kapanış) ------

    public function tetikleyiciSecenekYanitiIsle(StudentProfile $profile, KapanisKodu $yanit, Persona $persona): FlowOutcome
    {
        $profile->setPendingFlowStep(null);

        return match ($yanit) {
            KapanisKodu::K1 => new FlowOutcome(EngagementFlowMessages::kapanisK1($persona), null, WhatsAppTemplate::KapanisK1),
            KapanisKodu::K2 => new FlowOutcome(EngagementFlowMessages::kapanisK2($persona), null, WhatsAppTemplate::KapanisK2),
            KapanisKodu::K3 => new FlowOutcome(EngagementFlowMessages::kapanisK3($persona), null, WhatsAppTemplate::KapanisK3),
            KapanisKodu::K4 => new FlowOutcome(EngagementFlowMessages::kapanisK4($persona), null, WhatsAppTemplate::KapanisK4),
        };
    }

    // --- Bölüm 5: 72 saat yanıtsızlık hatırlatması (aksiyon alınabilir) ----------------

    /**
     * "Evet" dalı, M1/T2 Normal'in barrier kapanışıyla AYNI e-kampüs yönlendirmesini
     * ({@see WhatsAppTemplate::KapanisK1}) kullanır — uzun süre pasif bir öğrenci "evet
     * çalışmak istiyorum" dediğinde asıl faydalı adım bu, yeni bir şablon gerekmiyor.
     * "1 hafta sonra"/"Mesaj göndermeyin" dalları, Zaman dalının "ara vermek istiyorum"
     * sonrası dönüş süresi sorusuyla ({@see self::zamanDonusSuresiYanitiIsle()}) AYNI
     * `silence_until`/`ZamanDonusKapanis` mekanizmasını kullanır.
     */
    public function hatirlatma72SaatYanitiIsle(StudentProfile $profile, Hatirlatma72SaatResponse $yanit, \DateTimeImmutable $simdi, Persona $persona): FlowOutcome
    {
        $profile->setPendingFlowStep(null);

        return match ($yanit) {
            Hatirlatma72SaatResponse::Evet => new FlowOutcome(EngagementFlowMessages::kapanisK1($persona), null, WhatsAppTemplate::KapanisK1),
            Hatirlatma72SaatResponse::BirHaftaSonra => $this->hatirlatma72SaatSonraErtelendi($profile, $simdi, 7, $persona),
            Hatirlatma72SaatResponse::MesajGonderilmesin => $this->hatirlatma72SaatSonraErtelendi($profile, $simdi, self::SESSIZLIK_SURESIZ_GUN, $persona),
        };
    }

    private function hatirlatma72SaatSonraErtelendi(StudentProfile $profile, \DateTimeImmutable $simdi, int $gun, Persona $persona): FlowOutcome
    {
        $profile->setSilenceUntil($simdi->modify(sprintf('+%d days', $gun)));
        $profile->setCurrentState(StudentState::AraVermis);

        return new FlowOutcome(EngagementFlowMessages::zamanDonusKapanis($persona), null, WhatsAppTemplate::ZamanDonusKapanis);
    }
}
