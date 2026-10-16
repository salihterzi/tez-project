<?php

namespace App\Enum;

/**
 * Bir tetikleyicinin (T1-T4) öncelik/önem sınıflaması — yalnızca
 * {@see \App\Service\MessageFrequencyPolicy}'nin "yalnızca önemli olanlar" tercihini
 * uygularken hangi tetikleyicilerin "önemli" sayılacağına karar vermek için kullanılır.
 *
 * Not: Eskiden "SenderTone" (Sistem/Eğitmen) olarak modellenmişti — kullanıcı kararıyla
 * ("eğitmen imzalı mesaj olmayacak") o kısım tamamen kaldırıldı; gerçek gönderimde hiçbir
 * mesaj eğitmen/danışman kimliğiyle gitmeyecek, hepsi sistem üzerinden. Bu enum yalnızca
 * dahili önem sıralaması olarak kaldı — gönderen kimliğiyle bir ilgisi yok.
 *
 * T1/T2 = Yüksek, T3/T4 = Düşük sınıflaması kullanıcı tarafından "varsayım, kurum onayı
 * alınmadı" notuyla verildi — bkz. proje notları.
 */
enum TriggerPriority
{
    case Yuksek;
    case Dusuk;
}
