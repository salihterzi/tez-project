<?php

namespace App\Entity;

use App\Enum\BarrierType;
use App\Enum\FlowStep;
use App\Enum\GozlemYontemi;
use App\Enum\EnrollmentMotivation;
use App\Enum\MotivasyonResponse;
use App\Enum\Phase;
use App\Enum\PreferredStudyTime;
use App\Enum\ScheduledMessageKind;
use App\Enum\SocialComparisonOptin;
use App\Enum\StudentState;
use App\Enum\WeeklyHoursAvailable;
use App\Enum\ZamanResponse;
use App\Enum\ZorlukResponse;
use App\Repository\StudentProfileRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bir öğrencinin WhatsApp davranışsal müdahale akışındaki profili: form sırasında
 * bir kez toplanan tercihler ile durum makinesi/engel gözlemi gibi sık değişen
 * davranışsal alanları tek satırda tutar. `studentId`, {@see Ogrenci::$ogrenciNo}
 * ile paylaşılan birincil/yabancı anahtardır (bire-bir ilişki).
 */
#[ORM\Entity(repositoryClass: StudentProfileRepository::class)]
#[ORM\Table(name: 'student_profile')]
#[ORM\HasLifecycleCallbacks]
class StudentProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Ogrenci::class)]
    #[ORM\JoinColumn(name: 'student_id', referencedColumnName: 'ogrenci_no', nullable: false)]
    private Ogrenci $student;

    // --- form verileri (nadiren değişir) ---

    #[ORM\Column(name: 'student_term', type: 'smallint', nullable: true)]
    private ?int $studentTerm = null;

    #[ORM\Column(
        name: 'preferred_study_time',
        enumType: PreferredStudyTime::class,
        nullable: true,
        // 2026-11-10 düzeltmesi: burada 'Gece' yazılıydı — HİÇBİR YERDE olmayan bir değer
        // ({@see PreferredStudyTime} `Ogle = 'Öğle'` diyor, YBS formu Soru 6 "Öğle" sunuyor,
        // DB kolonu da 'Öğle' içeriyor ve 3 kayıt bu değeri kullanıyor). `columnDefinition`
        // yalnızca şema üretimini etkilediği için okuma/yazma bozulmuyordu, ama
        // `doctrine:schema:update --force` çalıştıran biri kolonu 'Gece'ye çevirip 'Öğle'
        // kayıtlarını SİLECEKTİ. enrollment_motivation ile aynı sınıf hata: elle yazılmış enum
        // dizgisi kaynağından sapmış.
        columnDefinition: "ENUM('Sabah','Öğle','Akşam','Değişken') DEFAULT NULL",
    )]
    private ?PreferredStudyTime $preferredStudyTime = null;

    /**
     * YBS kayıt anketi (`YBS_Arastirma_Kayit_Formu (2).docx`) Soru 5: "Haftada eğitime
     * ortalama kaç gün ayırabilirsiniz?" — 2026-10-05'te yeniden oluşturuldu: önceki tur bu
     * alanı saat aralığı (`1-2`/`3-5`/`6+`) olarak tasarlamıştı, ama asıl form gün SAYISI
     * soruyormuş (bkz. {@see WeeklyHoursAvailable}'ın docblock'u) — eski (kullanılmayan,
     * hiç toplanmamış) değerler kaldırılıp formun gerçek seçenekleriyle değiştirildi. Bu alanı
     * nasıl kullanacağımız (tempo/içerik önerisi — formun "Kullanım Amacı" sütunu) henüz
     * kararlaştırılmadı, bkz. `roadmap-faz.md` Faz B.
     */
    #[ORM\Column(
        name: 'weekly_hours_available',
        enumType: WeeklyHoursAvailable::class,
        nullable: true,
        columnDefinition: "ENUM('1 gün','2 gün','3 gün','4 gün','5 gün','6 gün','Her gün') DEFAULT NULL",
    )]
    private ?WeeklyHoursAvailable $weeklyHoursAvailable = null;

    #[ORM\Column(
        name: 'social_comparison_optin',
        enumType: SocialComparisonOptin::class,
        nullable: true,
        columnDefinition: "ENUM('Evet','Hayır','Fark etmez') DEFAULT NULL",
    )]
    private ?SocialComparisonOptin $socialComparisonOptin = null;

    #[ORM\Column(
        name: 'enrollment_motivation',
        enumType: EnrollmentMotivation::class,
        nullable: true,
        // 2026-11-10: Soru 8 "ders beklentisi" → "kayıt motivasyonu" (bkz. EnrollmentMotivation
        // docblock'u ve migration Version20261110140000). Değerler burada SABİT yazılı
        // olduğu için enum değişikliği bu satıra da yansıtılmak ZORUNDA — aksi halde
        // doctrine:schema:validate kolonu eski değerlere geri almak ister.
        columnDefinition: "ENUM('Maaşımı yükseltmek','Unvanımı değiştirmek','Kendimi gerçekleştirmek','Alanımla ilgili bilgi edinmek') DEFAULT NULL",
    )]
    private ?EnrollmentMotivation $enrollmentMotivation = null;

    /**
     * Öğrencinin kesin saat olarak beyan ettiği tercih edilen mesaj saati —
     * {@see self::$preferredStudyTime}'ın kaba kategorisinden (Sabah/Akşam/Gece/Değişken) ayrı,
     * tam saat (örn. "21:00"). Set edildiğinde {@see self::$preferenceSetAt} otomatik
     * güncellenir (bkz. {@see self::setPreferredMessageTime()}) — {@see
     * \App\Service\MessageTimePriorityResolver}'daki 2 haftalık deneme penceresi buradan
     * hesaplanır.
     */
    #[ORM\Column(name: 'preferred_message_time', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $preferredMessageTime = null;

    /** {@see self::$preferredMessageTime}'ın en son ne zaman set edildiği — bkz. o alanın docblock'u. */
    #[ORM\Column(name: 'preference_set_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $preferenceSetAt = null;

    // --- durum makinesi / davranışsal (sık değişir) ---

    #[ORM\Column(
        name: 'current_state',
        enumType: StudentState::class,
        columnDefinition: "ENUM('YENİ','AKTİF','YAVAŞLAYAN','PASİF','KOHORT_GERİSİNDE','ARA_VERMİŞ') NOT NULL DEFAULT 'YENİ'",
    )]
    private StudentState $currentState = StudentState::Yeni;

    #[ORM\Column(
        name: 'last_barrier_type',
        enumType: BarrierType::class,
        nullable: true,
        columnDefinition: "ENUM('zaman','zorluk','motivasyon') DEFAULT NULL",
    )]
    private ?BarrierType $lastBarrierType = null;

    /**
     * `last_barrier_type` en son ne zaman set edildi — tarihsel/bilgi amaçlı kayıt. M1'in tekrar
     * sorulup sorulmayacağı artık bu tarihe değil `current_state`'e bağlı (2026-10-18, kullanıcı
     * kararı — bkz. {@see \App\Repository\StudentProfileRepository::findStudentIdsWithValidBarrierType()}).
     * `last_barrier_type` set edilirken her zaman birlikte güncellenir (bkz.
     * {@see self::setLastBarrierType()}), ayrıca elle set edilmez.
     */
    #[ORM\Column(name: 'last_barrier_type_set_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastBarrierTypeSetAt = null;

    /**
     * Öğrenci şu an M1 (engel teşhisi) senaryosunun bir adımında mı, hangisinde? `null`:
     * bekleyen bir senaryo yok. Bkz. {@see \App\Service\EngagementFlowService}.
     */
    #[ORM\Column(
        name: 'pending_flow_step',
        enumType: FlowStep::class,
        nullable: true,
        // 2026-11-10 düzeltmesi: `HATIRLATMA_72_SAAT_YANITI_BEKLENIYOR` burada EKSİKTİ.
        // {@see FlowStep} onu 10. case olarak taşıyor ve DB kolonunda da var; yalnızca bu
        // elle yazılmış dizgi geride kalmıştı. `doctrine:schema:update --force` çalıştıran
        // biri o adımı kolondan DÜŞÜRÜRDÜ — 72 saat hatırlatma yanıtı bekleyen öğrencilerin
        // akış durumu kaybolurdu (şu an 0 satır kullanıyor, ama dönem içinde kullanılacak).
        columnDefinition: "ENUM('ENGEL_BEKLENIYOR','ZAMAN_TAKIBI_BEKLENIYOR','ZAMAN_GUN_SAATI_BEKLENIYOR','ZAMAN_DONUS_SURESI_BEKLENIYOR','ZORLUK_TAKIBI_BEKLENIYOR','ZORLUK_NOT_BEKLENIYOR','MOTIVASYON_TAKIBI_BEKLENIYOR','T1_YANITI_BEKLENIYOR','TETIKLEYICI_SECENEK_YANITI_BEKLENIYOR','HATIRLATMA_72_SAAT_YANITI_BEKLENIYOR') DEFAULT NULL",
    )]
    private ?FlowStep $pendingFlowStep = null;

    /**
     * Bu akış kapanışı için "akış tamamlandı" bilgilendirmesinin gönderilip gönderilmediği
     * (2026-10-28, kullanıcı kararı: "her akış kapandığında BİR KERE gelmeli").
     *
     * {@see self::setPendingFlowStep()} içinde, boş OLMAYAN bir adım atandığında otomatik
     * `false`'a döner — yani yeni bir akış başladığında bilgilendirme yeniden "borçlu" hâle
     * gelir ve o akış kapandıktan sonraki ilk serbest metinde tekrar gönderilir.
     */
    #[ORM\Column(name: 'akis_tamamlandi_bildirildi', type: 'boolean', options: ['default' => false])]
    private bool $akisTamamlandiBildirildi = false;

    /**
     * "Bugünlük mesajlaşma sınırına ulaşıldı" bildiriminin bugün gönderilip gönderilmediği
     * (2026-10-28, kullanıcı kararı). Önceden sınır aşıldıktan sonra gelen HER mesajda tekrar
     * gönderiliyordu — mesajlaşmayı kesmesi gereken mekanizma sınırsız mesaj üretiyordu.
     *
     * Boolean değil TARİH: {@see self::$dailyMessageAt} ile aynı mantıkla gün karşılaştırması
     * yapılır ({@see self::gunlukSinirBildirildiMi()}), böylece gece yarısı ayrı bir sıfırlama
     * işine gerek kalmaz.
     */
    #[ORM\Column(name: 'gunluk_sinir_bildirildi_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $gunlukSinirBildirildiAt = null;

    /**
     * Bu oturumda "lütfen seçeneklerden birini seçin" hatırlatmasının gönderilip gönderilmediği
     * (2026-10-28, kullanıcı kararı: oturumda bir kez, devam ederse sessiz kalınır).
     *
     * {@see self::oturumuBaslat()} içinde sıfırlanır — yani her yeni proaktif mesajla birlikte
     * öğrenci yeniden bir hatırlatma hakkı kazanır.
     */
    #[ORM\Column(name: 'secenek_hatirlatmasi_gonderildi', type: 'boolean', options: ['default' => false])]
    private bool $secenekHatirlatmasiGonderildi = false;

    /**
     * Bu oturumu BAŞLATAN proaktif mesajın türü (2026-10-30, kullanıcı kararı) —
     * {@see self::oturumuBaslat()} ile yazılır, yani her zaman gerçekten gönderilmiş bir
     * mesajın türüdür.
     *
     * Niye gerekiyor: günlük kontrol kanalı bilerek `pending_flow_step` SET ETMEZ
     * (2026-10-19, kullanıcı kararı — cevap vermeyen öğrencide adım sonsuza kadar dolu kalıp
     * M1'i sessizce bir daha hiç tetiklenemez hale getiriyordu). Dolayısıyla webhook, bekleyen
     * adım boş olduğunda "gerçekten tamamlanmış bir akış" ile "günlük kontrol mesajı" arasını
     * ayırt edemiyordu ve ikisine de "akış tamamlandı" diyordu — oysa hiç konuşma başlamamıştı.
     * Bu alan o ayrımı `pending_flow_step`'e dokunmadan kuruyor
     * ({@see \App\Controller\WhatsAppWebhookController::oturumBildirimiGonder()}).
     *
     * Tarihçe: 28 Ekim'de eklendi (`Version20261028160000`), 29 Ekim'de kaldırıldı
     * (`Version20261029090000`) çünkü buton QUICK_REPLY'dan URL'ye çevrilince "yanıt
     * beklenmiyor" sayıldı; 30 Ekim'de geri getirildi (`Version20261030090000`) — `_v3`
     * gövdesi hâlâ serbest metne davet ediyor ("1-7 arası bir sayı yazman yeterli"), yani
     * sayı OLMAYAN serbest metin hâlâ yanlış dala düşüyordu.
     */
    #[ORM\Column(name: 'oturum_kaynagi', type: 'string', length: 32, nullable: true, enumType: ScheduledMessageKind::class)]
    private ?ScheduledMessageKind $oturumKaynagi = null;

    /**
     * Mevcut oturumun korelasyon anahtarı (2026-10-30, kullanıcı kararı). Ayrı bir "oturum"
     * tablosu YOK — oturumun kendisi hâlâ bu satırın anlık durumu; bu alan yalnızca
     * {@see \App\Service\OturumMesajLoglayici}'nin bir oturuma ait tüm mesajları (proaktif
     * açılış + sonraki gelen/giden) `oturum_mesaji` tablosunda GRUPLAYABİLMESİ için
     * {@see self::oturumuBaslat()} içinde üretilir. Eski bir oturumun ID'si, yeni bir
     * oturum başladığında üzerine yazılır — geçmiş mesajlar zaten kendi satırlarına
     * loglandığı için bu kaybı etkilemez.
     */
    #[ORM\Column(name: 'oturum_id', type: 'string', length: 32, nullable: true)]
    private ?string $oturumId = null;

    /**
     * Bu oturumda öğrenci haftalık gün sayısını (1-7) güncelledi mi? (2026-10-30, kullanıcı
     * kararı: "oturumda bir kez tercih değişikliği".)
     *
     * İkinci sayı ayarı DEĞİŞTİRMEZ ve onay mesajı üretmez; serbest metin gibi bildirim dalına
     * düşer ve orada kendi metnini bulur ("her mesajda bir kez güncelleyebiliyoruz"). Gerekçe:
     * onay mesajı günlük/oturum sayacından muaf olduğu için (2026-10-18, kullanıcı kararı —
     * tercih güncellemesi bir sohbet alışverişi değil) hiçbir üst sınırı yoktu; öğrenci
     * `3 → 5 → 2 → 7` yazarak sınırsız sayıda giden mesaj üretebiliyordu. Bu, 28 Ekim'de
     * günlük sınır duyurusunda bulunan arızanın aynısı: mesajlaşmayı kesmesi gereken
     * mekanizmanın kendisi sınırsız mesaj üretiyor.
     *
     * Bilinen bedeli (kullanıcı kararıyla kabul edildi): yanlış sayı yazan öğrenci aynı
     * oturumda düzeltemez — ayarı bir sonraki proaktif mesaja kadar yanlış kalır. Bildirim
     * metni en azından düzeltmenin NE ZAMAN mümkün olduğunu söylüyor.
     *
     * {@see self::oturumuBaslat()} içinde sıfırlanır.
     */
    #[ORM\Column(name: 'oturumda_sayi_yazildi', type: 'boolean', options: ['default' => false])]
    private bool $oturumdaSayiYazildi = false;


    // --- M1 (engel teşhisi) veri alanları — Engel_Teshisi_Mesaj_Sablonu.docx "Toplanacak
    // Veri Alanları" tablosu (barrier_type zaten current_state'in üstünde last_barrier_type
    // olarak var; goal_status ayrı bir kolon açmadan motivationBarrierChoice ile birleştirildi,
    // bkz. o alanın docblock'u) ---

    #[ORM\Column(
        name: 'time_barrier_choice',
        enumType: ZamanResponse::class,
        nullable: true,
        columnDefinition: "ENUM('haftada_1_kisa_oturum','tempo_ayni_kalsin','ara_vermek_istiyorum') DEFAULT NULL",
    )]
    private ?ZamanResponse $timeBarrierChoice = null;

    #[ORM\Column(
        name: 'difficulty_barrier_choice',
        enumType: ZorlukResponse::class,
        nullable: true,
        columnDefinition: "ENUM('materyali_gozden_gecir','notumu_birak') DEFAULT NULL",
    )]
    private ?ZorlukResponse $difficultyBarrierChoice = null;

    /** "Notumu bırak, sonra değerlendirilsin" seçilirse alınan serbest metin. */
    #[ORM\Column(name: 'difficulty_note_text', type: 'text', nullable: true)]
    private ?string $difficultyNoteText = null;

    #[ORM\Column(
        name: 'motivation_barrier_choice',
        enumType: MotivasyonResponse::class,
        nullable: true,
        // 2026-11-10 düzeltmesi: `hedef_degisti` burada FAZLAYDI. "Değişti" seçeneği
        // 2026-10-03'te tamamen kaldırılmıştı ({@see MotivasyonResponse} iki case taşıyor,
        // DB kolonu da iki değerli); yalnızca bu dizgi güncellenmemişti.
        columnDefinition: "ENUM('hedef_hala_gecerli','emin_degil') DEFAULT NULL",
    )]
    private ?MotivasyonResponse $motivationBarrierChoice = null;

    /**
     * Faz başına (Normal / Ara Sınav Öncesi / Final Öncesi) hesaplanan gözlem saati
     * (Zamanlama_Kisisellestirme_Karar_Mantigi.docx Bölüm 9). Bu alanları PHP YAZMAZ — Python
     * veri pipeline'ı (`mesaj_zamanlamasi_hibrit.py`, `FAZ` bazında `tahmini_saat`) kendisi
     * hesaplayıp doğrudan buraya yazar; PHP yalnızca okur.
     *
     * Kullanıcı kararıyla 4 kaba dilime (06-12/12-18/18-24/00-06) İNDİRGENMEZ — Python'un
     * ürettiği tahmini saat (örn. "14:37") olduğu gibi TIME olarak saklanır; kabalaştırma
     * (bucketing) bir önceki turda vardı, kaldırıldı.
     *
     * Üç ayrı kolon olmasının nedeni: Phase enum'ı da Python'un FAZ tanımıyla birebir eşleşecek
     * şekilde `Ara_Sinav_Oncesi`/`Final_Oncesi` olarak ikiye ayrıldı (bkz. {@see \App\Enum\Phase})
     * — vize ve final dönemlerinin çalışma saati örüntüsü farklı olabilir. T1-T4 iş kuralları
     * için bu ayrımın önemi yok ({@see \App\Enum\Phase::sinavOncesiMi()} ikisini de aynı
     * "sınav öncesi" davranışına indirger); ayrım yalnızca gözlem verisi için var.
     *
     * Her saat kolonunun yanında, o tahminin {@see \App\Enum\GozlemYontemi::SecenekBBireysel}
     * (öğrencinin kendi etkileşiminden) mi yoksa {@see \App\Enum\GozlemYontemi::SecenekAFallback}
     * (popülasyon ortalaması) mı türediğini tutan bir `*_yontem` kolonu vardır — bunu da PHP
     * YAZMAZ, Python aynı turda birlikte yazar. {@see \App\Service\MessageTimePriorityResolver}
     * gözlenen saatin tercihin önüne geçip geçemeyeceğine bu etikete göre karar verir.
     */
    #[ORM\Column(name: 'observed_time_window_normal', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowNormal = null;

    #[ORM\Column(
        name: 'observed_time_window_normal_yontem',
        enumType: GozlemYontemi::class,
        nullable: true,
        columnDefinition: "ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL",
    )]
    private ?GozlemYontemi $observedTimeWindowNormalYontem = null;

    /** Ara sınav öncesi fazından hesaplanan gözlem saati — bkz. {@see self::$observedTimeWindowNormal}. */
    #[ORM\Column(name: 'observed_time_window_ara_sinav_oncesi', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowAraSinavOncesi = null;

    #[ORM\Column(
        name: 'observed_time_window_ara_sinav_oncesi_yontem',
        enumType: GozlemYontemi::class,
        nullable: true,
        columnDefinition: "ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL",
    )]
    private ?GozlemYontemi $observedTimeWindowAraSinavOncesiYontem = null;

    /** Final öncesi fazından hesaplanan gözlem saati — bkz. {@see self::$observedTimeWindowNormal}. */
    #[ORM\Column(name: 'observed_time_window_final_oncesi', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $observedTimeWindowFinalOncesi = null;

    #[ORM\Column(
        name: 'observed_time_window_final_oncesi_yontem',
        enumType: GozlemYontemi::class,
        nullable: true,
        columnDefinition: "ENUM('secenek_a_fallback','secenek_b_bireysel') DEFAULT NULL",
    )]
    private ?GozlemYontemi $observedTimeWindowFinalOncesiYontem = null;

    /**
     * Gün bazlı kişiselleştirme (Python: `mesaj_zamanlamasi_gun_hibrit.py`, faz başına top-N
     * gün). Bu alanı da PHP YAZMAZ — Python doğrudan buraya JSON olarak yazar, PHP yalnızca
     * okur (henüz hiçbir PHP mantığı bunu tüketmiyor — bkz. proje notları, kullanıcı isteğiyle
     * yalnızca alan/kolon olarak eklendi).
     *
     * Beklenen şekil, faz adı (Phase enum değeri) → sıralı gün listesi:
     * ```json
     * {"Normal": ["Pazartesi", "Çarşamba", "Cuma"], "Ara_Sinav_Oncesi": ["Salı", "Perşembe"], "Final_Oncesi": ["Pazar"]}
     * ```
     */
    #[ORM\Column(name: 'observed_days', type: 'json', nullable: true)]
    private ?array $observedDays = null;

    #[ORM\Column(name: 'silence_until', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $silenceUntil = null;

    #[ORM\Column(name: 'last_message_sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastMessageSentAt = null;

    #[ORM\Column(name: 'messages_sent_this_week', type: 'smallint')]
    private int $messagesSentThisWeek = 0;

    /**
     * O öğrenciyle o GÜN (yön -- gelen/giden, tür -- proaktif/reaktif fark etmez) karşılıklı
     * gönderilen TOPLAM mesaj sayısı (2026-10-18, kullanıcı kararı; aynı gün genişletildi:
     * önce sadece reaktif akış yanıtlarını kapsıyordu, artık öğrencinin gelen mesajları VE
     * proaktif M1/T1-T4 gönderimi de dahil). İSTİSNALAR: Sınav Günü / 72 saat hatırlatması
     * (zorunlu bilgilendirme kanalı, {@see \App\Enum\MessageChannel}) VE "Merhaba" ile
     * susturmayı kendi isteğiyle kaldırma alışverişi ({@see \App\Controller\WhatsAppWebhookController::susturulmusOgrenciyiYenidenEtkinlestir()})
     * -- bunların hiçbiri bu sayaca dahil değil. {@see self::$messagesSentThisWeek}'ten KASITLI olarak AYRI: o sayaç SADECE
     * proaktif gönderimi sayıp YENİ bir T1-T4 kararını haftalık olarak sınırlıyor
     * ({@see \App\Service\MessageFrequencyPolicy}); bu alan ise bir GÜNDE o öğrenciyle toplam
     * ne kadar mesaj trafiği oluştuğunu (aşırı uzun/sık bir diyaloğa sürüklenme riskini)
     * sınırlıyor -- ikisi birbirinin yerine geçmez, İKİSİ DE ayrı ayrı uygulanır. Haftalık
     * sayaçla AYNI "tazeleme" deseni (bkz. {@see self::recordMessageSent()}) kullanılır, ama
     * hafta değil GÜN karşılaştırılır. Sınır değeri (10) ve uygulanma noktaları
     * {@see \App\Controller\WhatsAppWebhookController} ve {@see \App\Command\DispatchScheduledMessagesCommand}'da.
     */
    #[ORM\Column(name: 'daily_message_count', type: 'smallint')]
    private int $dailyMessageCount = 0;

    /** {@see self::$dailyMessageCount}'ın en son ne zaman artırıldığı -- gün karşılaştırması bu alana göre yapılır. */
    #[ORM\Column(name: 'daily_message_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dailyMessageAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Ogrenci $student)
    {
        $this->student = $student;
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getStudent(): Ogrenci
    {
        return $this->student;
    }

    public function getStudentTerm(): ?int
    {
        return $this->studentTerm;
    }

    public function setStudentTerm(?int $studentTerm): static
    {
        $this->studentTerm = $studentTerm;

        return $this;
    }

    public function getPreferredStudyTime(): ?PreferredStudyTime
    {
        return $this->preferredStudyTime;
    }

    public function setPreferredStudyTime(?PreferredStudyTime $preferredStudyTime): static
    {
        $this->preferredStudyTime = $preferredStudyTime;

        return $this;
    }

    public function getWeeklyHoursAvailable(): ?WeeklyHoursAvailable
    {
        return $this->weeklyHoursAvailable;
    }

    public function setWeeklyHoursAvailable(?WeeklyHoursAvailable $weeklyHoursAvailable): static
    {
        $this->weeklyHoursAvailable = $weeklyHoursAvailable;

        return $this;
    }

    public function getSocialComparisonOptin(): ?SocialComparisonOptin
    {
        return $this->socialComparisonOptin;
    }

    public function setSocialComparisonOptin(?SocialComparisonOptin $socialComparisonOptin): static
    {
        $this->socialComparisonOptin = $socialComparisonOptin;

        return $this;
    }

    public function getEnrollmentMotivation(): ?EnrollmentMotivation
    {
        return $this->enrollmentMotivation;
    }

    public function setEnrollmentMotivation(?EnrollmentMotivation $enrollmentMotivation): static
    {
        $this->enrollmentMotivation = $enrollmentMotivation;

        return $this;
    }

    public function getPreferredMessageTime(): ?\DateTimeImmutable
    {
        return $this->preferredMessageTime;
    }

    /**
     * `null` dışında bir değer set edildiğinde {@see self::$preferenceSetAt} otomatik olarak
     * "şimdi"ye güncellenir (2 haftalık deneme penceresi buradan hesaplanır) — ayrıca elle set
     * edilmesi gerekmez. `null` set edilirse zaman damgası da temizlenir.
     */
    public function setPreferredMessageTime(?\DateTimeImmutable $preferredMessageTime): static
    {
        $this->preferredMessageTime = $preferredMessageTime;
        $this->preferenceSetAt = null !== $preferredMessageTime ? new \DateTimeImmutable() : null;
        $this->touch();

        return $this;
    }

    public function getPreferenceSetAt(): ?\DateTimeImmutable
    {
        return $this->preferenceSetAt;
    }

    public function getCurrentState(): StudentState
    {
        return $this->currentState;
    }

    public function setCurrentState(StudentState $currentState): static
    {
        $this->currentState = $currentState;
        $this->touch();

        return $this;
    }

    public function getLastBarrierType(): ?BarrierType
    {
        return $this->lastBarrierType;
    }

    /**
     * `null` dışında bir değer set edildiğinde {@see self::$lastBarrierTypeSetAt} otomatik
     * olarak "şimdi"ye güncellenir (geçerlilik penceresi buradan hesaplanır) — ayrıca elle
     * set edilmesi gerekmez. `null` set edilirse (M1'in tekrar sorulmasını zorlamak için)
     * zaman damgası da temizlenir.
     */
    public function setLastBarrierType(?BarrierType $lastBarrierType): static
    {
        $this->lastBarrierType = $lastBarrierType;
        $this->lastBarrierTypeSetAt = null !== $lastBarrierType ? new \DateTimeImmutable() : null;
        $this->touch();

        return $this;
    }

    public function getLastBarrierTypeSetAt(): ?\DateTimeImmutable
    {
        return $this->lastBarrierTypeSetAt;
    }

    public function getPendingFlowStep(): ?FlowStep
    {
        return $this->pendingFlowStep;
    }

    public function setPendingFlowStep(?FlowStep $pendingFlowStep): static
    {
        // Yeni bir akış başlıyorsa (boş olmayan adım), "akış tamamlandı" bilgilendirmesi
        // yeniden borçlu hâle gelir — o akış kapandıktan sonraki ilk serbest metinde gider.
        if (null !== $pendingFlowStep) {
            $this->akisTamamlandiBildirildi = false;
        }

        $this->pendingFlowStep = $pendingFlowStep;
        $this->touch();

        return $this;
    }

    public function akisTamamlandiBildirildiMi(): bool
    {
        return $this->akisTamamlandiBildirildi;
    }

    public function akisTamamlandiBildirimiKaydet(): static
    {
        $this->akisTamamlandiBildirildi = true;
        $this->touch();

        return $this;
    }

    /** Günlük sınır bildirimi BUGÜN gönderildi mi? Gün değiştiyse otomatik `false`. */
    public function gunlukSinirBildirildiMi(\DateTimeImmutable $simdi): bool
    {
        return null !== $this->gunlukSinirBildirildiAt
            && $this->gunlukSinirBildirildiAt->format('Y-m-d') === $simdi->format('Y-m-d');
    }

    public function gunlukSinirBildirimiKaydet(\DateTimeImmutable $simdi): static
    {
        $this->gunlukSinirBildirildiAt = $simdi;
        $this->touch();

        return $this;
    }

    public function getTimeBarrierChoice(): ?ZamanResponse
    {
        return $this->timeBarrierChoice;
    }

    public function setTimeBarrierChoice(?ZamanResponse $timeBarrierChoice): static
    {
        $this->timeBarrierChoice = $timeBarrierChoice;
        $this->touch();

        return $this;
    }

    public function getDifficultyBarrierChoice(): ?ZorlukResponse
    {
        return $this->difficultyBarrierChoice;
    }

    public function setDifficultyBarrierChoice(?ZorlukResponse $difficultyBarrierChoice): static
    {
        $this->difficultyBarrierChoice = $difficultyBarrierChoice;
        $this->touch();

        return $this;
    }

    public function getDifficultyNoteText(): ?string
    {
        return $this->difficultyNoteText;
    }

    public function setDifficultyNoteText(?string $difficultyNoteText): static
    {
        $this->difficultyNoteText = $difficultyNoteText;
        $this->touch();

        return $this;
    }

    public function getMotivationBarrierChoice(): ?MotivasyonResponse
    {
        return $this->motivationBarrierChoice;
    }

    public function setMotivationBarrierChoice(?MotivasyonResponse $motivationBarrierChoice): static
    {
        $this->motivationBarrierChoice = $motivationBarrierChoice;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowNormal(): ?\DateTimeImmutable
    {
        return $this->observedTimeWindowNormal;
    }

    public function setObservedTimeWindowNormal(?\DateTimeImmutable $observedTimeWindowNormal): static
    {
        $this->observedTimeWindowNormal = $observedTimeWindowNormal;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowNormalYontem(): ?GozlemYontemi
    {
        return $this->observedTimeWindowNormalYontem;
    }

    public function setObservedTimeWindowNormalYontem(?GozlemYontemi $observedTimeWindowNormalYontem): static
    {
        $this->observedTimeWindowNormalYontem = $observedTimeWindowNormalYontem;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowAraSinavOncesi(): ?\DateTimeImmutable
    {
        return $this->observedTimeWindowAraSinavOncesi;
    }

    public function setObservedTimeWindowAraSinavOncesi(?\DateTimeImmutable $observedTimeWindowAraSinavOncesi): static
    {
        $this->observedTimeWindowAraSinavOncesi = $observedTimeWindowAraSinavOncesi;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowAraSinavOncesiYontem(): ?GozlemYontemi
    {
        return $this->observedTimeWindowAraSinavOncesiYontem;
    }

    public function setObservedTimeWindowAraSinavOncesiYontem(?GozlemYontemi $observedTimeWindowAraSinavOncesiYontem): static
    {
        $this->observedTimeWindowAraSinavOncesiYontem = $observedTimeWindowAraSinavOncesiYontem;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowFinalOncesi(): ?\DateTimeImmutable
    {
        return $this->observedTimeWindowFinalOncesi;
    }

    public function setObservedTimeWindowFinalOncesi(?\DateTimeImmutable $observedTimeWindowFinalOncesi): static
    {
        $this->observedTimeWindowFinalOncesi = $observedTimeWindowFinalOncesi;
        $this->touch();

        return $this;
    }

    public function getObservedTimeWindowFinalOncesiYontem(): ?GozlemYontemi
    {
        return $this->observedTimeWindowFinalOncesiYontem;
    }

    public function setObservedTimeWindowFinalOncesiYontem(?GozlemYontemi $observedTimeWindowFinalOncesiYontem): static
    {
        $this->observedTimeWindowFinalOncesiYontem = $observedTimeWindowFinalOncesiYontem;
        $this->touch();

        return $this;
    }

    /**
     * {@see self::$observedTimeWindowNormal}/`...AraSinavOncesi`/`...FinalOncesi`'i tek bir
     * `Phase` parametresiyle okumak için — bkz. {@see \App\Service\MessageTimePriorityResolver}.
     * `Phase::SinavGunu` için gözlem kolonu yok, bu durumda `null` döner.
     */
    public function getObservedTimeWindowForPhase(Phase $faz): ?\DateTimeImmutable
    {
        return match ($faz) {
            Phase::Normal => $this->observedTimeWindowNormal,
            Phase::AraSinavOncesi => $this->observedTimeWindowAraSinavOncesi,
            Phase::FinalOncesi => $this->observedTimeWindowFinalOncesi,
            Phase::SinavGunu => null,
        };
    }

    /** {@see self::getObservedTimeWindowForPhase()}'in yöntem (secenek_a/secenek_b) karşılığı. */
    public function getObservedTimeWindowYontemForPhase(Phase $faz): ?GozlemYontemi
    {
        return match ($faz) {
            Phase::Normal => $this->observedTimeWindowNormalYontem,
            Phase::AraSinavOncesi => $this->observedTimeWindowAraSinavOncesiYontem,
            Phase::FinalOncesi => $this->observedTimeWindowFinalOncesiYontem,
            Phase::SinavGunu => null,
        };
    }

    public function getObservedDays(): ?array
    {
        return $this->observedDays;
    }

    public function setObservedDays(?array $observedDays): static
    {
        $this->observedDays = $observedDays;
        $this->touch();

        return $this;
    }

    public function getSilenceUntil(): ?\DateTimeImmutable
    {
        return $this->silenceUntil;
    }

    public function setSilenceUntil(?\DateTimeImmutable $silenceUntil): static
    {
        $this->silenceUntil = $silenceUntil;
        $this->touch();

        return $this;
    }

    public function getLastMessageSentAt(): ?\DateTimeImmutable
    {
        return $this->lastMessageSentAt;
    }

    public function setLastMessageSentAt(?\DateTimeImmutable $lastMessageSentAt): static
    {
        $this->lastMessageSentAt = $lastMessageSentAt;
        $this->touch();

        return $this;
    }

    public function getMessagesSentThisWeek(): int
    {
        return $this->messagesSentThisWeek;
    }

    public function setMessagesSentThisWeek(int $messagesSentThisWeek): static
    {
        $this->messagesSentThisWeek = $messagesSentThisWeek;
        $this->touch();

        return $this;
    }

    /**
     * GERÇEK bir PROAKTİF kişiselleştirilmiş mesaj (M1/T1-T4 varyantı) gönderildiğinde
     * çağrılmalı (zorunlu bilgilendirme DEĞİL — bkz. {@see \App\Enum\MessageChannel}).
     * `messages_sent_this_week`'i ISO hafta (Pazartesi başlangıç) bazında kendiliğinden
     * sıfırlayıp artırır — ayrı bir haftalık "sıfırlama" job'ına gerek yok: önceki gönderim
     * farklı bir ISO haftadaysa (ya da hiç gönderim yoksa) sayaç önce 0'a döner, sonra 1 artırılır.
     * Bu, YENİ bir T1-T4 kararının haftalık sıklık sınırı için kullanılır
     * ({@see \App\Service\MessageFrequencyPolicy}) — {@see self::$dailyMessageCount}'tan
     * (günlük TOPLAM trafik sınırı) KASITLI olarak ayrı, ikisi de ayrı ayrı uygulanır.
     */
    /**
     * SAYACIN YAN ÜRÜNÜ — TASARLANMIŞ BİR KURAL DEĞİL (2026-11-06'da belgelendi):
     * sayaç ISO hafta (Pazartesi) bazında sıfırlandığı ve karar motoru her gece çalıştığı
     * için, haftalık tavana tabi tetikleyiciler fiilen **her haftanın ilk uygun gününde**
     * (Pazartesi) gönderilir. Örnek (dönem başlangıcı 2027-02-23 Salı):
     *
     *   2 Mart Salı        T1 ilk kez ateşlenir (başlangıç+7), haftalık bütçe dolar
     *   8 Mart Pazartesi   sayaç sıfırlanır → ilk fırsatta yeniden
     *   15 Mart Pazartesi  aynı
     *
     * Yani ilk mesajdan sonra kadans kendiliğinden haftalık/Pazartesi'ye oturur ve
     * "Pazar gönderimi + Pazartesi tekrarı" gibi 1 günlük aralıklar pratikte oluşmaz.
     * ÖNEMLİ: bu davranış sayacın ISO-hafta tabanlı olmasına BAĞLI. Sayaç kayan 7 güne
     * çevrilirse kadans da değişir — o değişikliği yapan, tetikleyici aralıklarının
     * bozulacağını bilmeli.
     */
    public function recordMessageSent(\DateTimeImmutable $simdi): static
    {
        $ayniHaftaMi = null !== $this->lastMessageSentAt
            && $this->lastMessageSentAt->format('o-W') === $simdi->format('o-W');

        $this->messagesSentThisWeek = ($ayniHaftaMi ? $this->messagesSentThisWeek : 0) + 1;
        $this->lastMessageSentAt = $simdi;
        $this->touch();

        return $this;
    }

    /**
     * Öğrencinin BU ISO haftada (Pazartesi başlangıç) kaç tetikleyici-kaynaklı mesaj aldığını
     * döner -- `recordMessageSent()`'in YAZMA anında uyguladığı AYNI `'o-W'` karşılaştırmasını
     * OKUMA anında da uygular (salt okunur, sayaç durumunu DEĞİŞTİRMEZ).
     *
     * 2026-10-20 (kullanıcı kararı, gerçek bir bug'dan bulundu): bu metot eklenmeden önce
     * {@see \App\Service\MessageFrequencyPolicy::siklikUygunMu()} ham `messagesSentThisWeek`
     * sütununu DOĞRUDAN kullanıyordu — bu değer sadece YAZMA anında (yani bir sonraki GERÇEK
     * gönderimde) sıfırlanıyordu. Bir öğrenci haftanın son günü (örn. Pazar) 1 mesaj alırsa,
     * yeni hafta başladıktan (Pazartesi) SONRA bile sayaç hâlâ "1" okunuyordu — ve bunu
     * sıfırlayacak tek yer (`recordMessageSent()`) ZATEN bu sınır kontrolünün GEÇMESİNİ
     * gerektirdiği için öğrenci KALICI olarak "haftalık sıklık sınırında" kilitleniyordu.
     */
    public function haftalikMesajSayisi(\DateTimeImmutable $simdi): int
    {
        $ayniHaftaMi = null !== $this->lastMessageSentAt
            && $this->lastMessageSentAt->format('o-W') === $simdi->format('o-W');

        return $ayniHaftaMi ? $this->messagesSentThisWeek : 0;
    }

    /**
     * Öğrenciyle bugün (GÜN bazında, `$simdi`'nin tarihine göre) kaç mesaj (yön/tür fark
     * etmez — gelen, giden, proaktif, reaktif) ALINIP/GÖNDERİLDİĞİNİ döner -- `$simdi` farklı
     * bir güne denk geliyorsa (ya da hiç kayıt yoksa) 0 döner, sayaç DURUMU DEĞİŞTİRMEZ (salt
     * okunur) — {@see \App\Controller\WhatsAppWebhookController}'ın yeni bir mesaj
     * göndermeden/işlemeden ÖNCE günlük sınıra ulaşılıp ulaşılmadığını kontrol etmesi için.
     */
    public function gunlukMesajSayisi(\DateTimeImmutable $simdi): int
    {
        $bugunMu = null !== $this->dailyMessageAt
            && $this->dailyMessageAt->format('Y-m-d') === $simdi->format('Y-m-d');

        return $bugunMu ? $this->dailyMessageCount : 0;
    }

    /**
     * Yeni bir OTURUM başlatır (2026-10-28, kullanıcı kararı: "1 oturum, 1 proaktif mesajla
     * başlayan sohbet akışıdır"). Proaktif gönderim ({@see \App\Command\DispatchScheduledMessagesCommand})
     * sayacı ARTIRMAK yerine 1'e SIFIRLAR — böylece {@see \App\Service\MessageFrequencyPolicy::GUNLUK_UST_SINIR}
     * oturum başına uygulanır.
     *
     * Tasarımda günde en fazla bir oturum hedeflendiği için (günlük kontrol kanalı, o gün
     * tetikleyici kaynaklı mesaj gitmediyse devreye girer — bkz. {@see \App\Enum\ScheduledMessageKind})
     * bu sayaç fiilen hem oturum hem gün sınırı işlevi görür. Gün değişiminde kendiliğinden
     * sıfırlanması ({@see self::gunlukMesajSayisi()}) ek bir güvenlik olarak korunur.
     *
     * `$kaynak`, oturumu açan mesajın türü — {@see self::$oturumKaynagi}'nın tek yazarı
     * (2026-10-30, kullanıcı kararı). Bu metot YALNIZCA gerçek gönderim başarılı olduktan
     * sonra çağrıldığı için ({@see \App\Command\DispatchScheduledMessagesCommand}) alan her
     * zaman gerçekten gönderilmiş bir mesajı gösterir.
     */
    public function oturumuBaslat(\DateTimeImmutable $simdi, ScheduledMessageKind $kaynak): static
    {
        $this->dailyMessageCount = 1;
        $this->dailyMessageAt = $simdi;
        $this->oturumKaynagi = $kaynak;
        $this->oturumId = bin2hex(random_bytes(16));
        // "Oturumda bir kez" kuralına tabi bildirimler yeni oturumda yeniden hak kazanır.
        $this->secenekHatirlatmasiGonderildi = false;
        $this->akisTamamlandiBildirildi = false;
        $this->gunlukSinirBildirildiAt = null;
        // Tercih değişikliği hakkı da oturum başına: yeni oturumda yeniden kazanılır
        // (2026-10-30, kullanıcı kararı — bkz. {@see self::$oturumdaSayiYazildi}).
        $this->oturumdaSayiYazildi = false;
        $this->touch();

        return $this;
    }

    public function getOturumId(): ?string
    {
        return $this->oturumId;
    }

    public function getOturumKaynagi(): ?ScheduledMessageKind
    {
        return $this->oturumKaynagi;
    }

    public function oturumdaSayiYazildiMi(): bool
    {
        return $this->oturumdaSayiYazildi;
    }

    public function sayiYazildiginiKaydet(): static
    {
        $this->oturumdaSayiYazildi = true;
        $this->touch();

        return $this;
    }

    public function secenekHatirlatmasiGonderildiMi(): bool
    {
        return $this->secenekHatirlatmasiGonderildi;
    }

    public function secenekHatirlatmasiniKaydet(): static
    {
        $this->secenekHatirlatmasiGonderildi = true;
        $this->touch();

        return $this;
    }

    /**
     * Öğrenciyle bugün bir mesaj alınıp/gönderildiğinde (yön/tür fark etmez — öğrencinin
     * gelen mesajı, sistemin proaktif M1/T1-T4 gönderimi, ya da reaktif akış yanıtı) çağrılmalı
     * — Sınav Günü/72 saat hatırlatması İSTİSNA (bkz. {@see self::$dailyMessageCount}'un
     * docblock'u), onlarda ÇAĞRILMAZ. `daily_message_count`'u GÜN bazında kendiliğinden
     * sıfırlayıp artırır ({@see self::recordMessageSent()}'in haftalık sıfırlama deseniyle
     * AYNI mantık, sadece gün karşılaştırması).
     */
    public function gunlukMesajiKaydet(\DateTimeImmutable $simdi): static
    {
        $this->dailyMessageCount = $this->gunlukMesajSayisi($simdi) + 1;
        $this->dailyMessageAt = $simdi;
        $this->touch();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
