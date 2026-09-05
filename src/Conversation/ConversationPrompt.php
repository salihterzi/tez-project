<?php

namespace App\Conversation;

/**
 * Öğrenci destek asistanının kişiliği (system prompt).
 *
 * Hem gelen webhook yanıtları ({@see \App\Controller\WhatsAppWebhookController})
 * hem de dışarıdan başlatılan konuşmalar ({@see \App\Controller\OutboundController})
 * aynı personayı kullansın diye tek yerde tutulur.
 *
 * TODO: Faz 3'te öğrenci profiline göre dinamikleştirilecek.
 */
final class ConversationPrompt
{
    public const SYSTEM = 'Sen bir öğrenci destek asistanısın. Kısa, samimi ve destekleyici mesajlar yaz. Türkçe yanıt ver.';
}
