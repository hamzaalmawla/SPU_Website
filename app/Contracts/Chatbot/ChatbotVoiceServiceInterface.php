<?php

declare(strict_types=1);

namespace App\Contracts\Chatbot;

use App\DTOs\Chatbot\ChatbotLiveSessionDTO;
use App\DTOs\Chatbot\ChatbotLiveSessionRequestDTO;
use App\DTOs\Chatbot\ChatbotSpeechDTO;
use App\DTOs\Chatbot\ChatbotSpeechRequestDTO;
use App\DTOs\Chatbot\ChatbotTranscriptionDTO;
use App\DTOs\Chatbot\ChatbotTranscriptionRequestDTO;

/**
 * The assistant's voice: spoken questions in, replies read aloud out.
 *
 * A transcript is only text for the visitor to send; it is then asked like a
 * typed question, so every guard and limit of ChatbotServiceInterface::ask
 * applies to it. Only replies the assistant wrote (signed) are read aloud.
 * Neither method throws for provider failures: those come back as a status.
 */
interface ChatbotVoiceServiceInterface
{
    public function transcribe(ChatbotTranscriptionRequestDTO $request): ChatbotTranscriptionDTO;

    /**
     * A short-lived key for live transcription: the browser streams the
     * microphone to OpenAI while the visitor speaks, in a transcription-only
     * session (it cannot answer or chat). Same shares, pauses and daily cap
     * as recordings.
     */
    public function liveSession(ChatbotLiveSessionRequestDTO $request): ChatbotLiveSessionDTO;

    /**
     * One segment of a reply's spoken form, as audio: a whole file, or raw
     * PCM streamed as it is made when the request asks for it.
     */
    public function speak(ChatbotSpeechRequestDTO $request): ChatbotSpeechDTO;
}
