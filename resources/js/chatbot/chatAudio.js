/**
 * Audio helpers for the assistant's voice, free of the DOM and of Web Audio
 * so they can be unit tested (tests/js/chatbot.test.js).
 *
 * The Realtime transcription session takes PCM, 16-bit little-endian, mono,
 * 24 kHz; the speech endpoint streams the same format back. Browsers record
 * at their own rate (usually 48 kHz or 44.1 kHz) as floats.
 */

export const PCM_RATE = 24000;

/**
 * A resampler from the microphone's rate to `outRate`, kept across chunks
 * (the fractional read position carries over), with a two-tap average as a
 * light low-pass so a 2:1 reduction does not alias. Good enough for speech.
 */
export function createResampler(inRate, outRate = PCM_RATE) {
    const step = inRate / outRate;
    let position = 0;
    let previous = 0;

    return (input) => {
        if (!input?.length) {
            return new Float32Array(0);
        }
        if (inRate === outRate) {
            return Float32Array.from(input);
        }

        const output = [];
        while (position < input.length) {
            const index = Math.floor(position);
            const fraction = position - index;
            const current = input[index];
            const next = index + 1 < input.length ? input[index + 1] : current;
            const before = index > 0 ? input[index - 1] : previous;
            const sample = current + (next - current) * fraction;
            output.push(step >= 1.5 ? (sample + before) / 2 : sample);
            position += step;
        }
        position -= input.length;
        previous = input[input.length - 1];

        return Float32Array.from(output);
    };
}

/** Floats (-1..1) to 16-bit PCM samples. */
export function floatToPcm16(samples) {
    const pcm = new Int16Array(samples.length);
    for (let index = 0; index < samples.length; index++) {
        const value = Math.max(-1, Math.min(1, samples[index]));
        pcm[index] = value < 0 ? value * 0x8000 : value * 0x7fff;
    }

    return pcm;
}

/** Bytes to base64, in slices so a long buffer cannot overflow the call stack. */
export function bytesToBase64(bytes) {
    let binary = '';
    for (let offset = 0; offset < bytes.length; offset += 0x8000) {
        binary += String.fromCharCode.apply(null, bytes.subarray(offset, offset + 0x8000));
    }

    return btoa(binary);
}

/**
 * Turns streamed PCM bytes into float samples. Network chunks may split a
 * sample between them; the odd byte waits for the next chunk.
 */
export function createPcm16Decoder() {
    let carry = null;

    return (chunk) => {
        let bytes = chunk instanceof Uint8Array ? chunk : new Uint8Array(chunk);
        if (carry !== null) {
            const joined = new Uint8Array(bytes.length + 1);
            joined[0] = carry;
            joined.set(bytes, 1);
            bytes = joined;
            carry = null;
        }
        if (bytes.length % 2 === 1) {
            carry = bytes[bytes.length - 1];
            bytes = bytes.subarray(0, bytes.length - 1);
        }

        const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
        const samples = new Float32Array(bytes.length / 2);
        for (let index = 0; index < samples.length; index++) {
            samples[index] = view.getInt16(index * 2, true) / 0x8000;
        }

        return samples;
    };
}

/**
 * What a Realtime transcription event means for the widget: partial text,
 * the final transcript of a committed turn, a failure, or nothing of interest.
 */
export function readTranscriptionEvent(raw) {
    let event;
    try {
        event = typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch {
        return { kind: 'ignore' };
    }

    switch (event?.type) {
        case 'conversation.item.input_audio_transcription.delta':
            return { kind: 'partial', itemId: event.item_id ?? null, text: typeof event.delta === 'string' ? event.delta : '' };
        case 'conversation.item.input_audio_transcription.completed':
            return { kind: 'final', itemId: event.item_id ?? null, text: typeof event.transcript === 'string' ? event.transcript.trim() : '' };
        case 'conversation.item.input_audio_transcription.failed':
            return { kind: 'failed', itemId: event.item_id ?? null };
        case 'input_audio_buffer.committed':
            return { kind: 'committed', itemId: event.item_id ?? null };
        case 'error':
            return { kind: 'error', message: typeof event.error?.message === 'string' ? event.error.message : '' };
        default:
            return { kind: 'ignore' };
    }
}

/**
 * Whether to ask for streamed PCM (about 384 kbit/s) or one mp3 file per
 * segment (128 kbit/s, but heard only once it has all arrived). Slow or
 * data-saving connections get mp3.
 */
export function prefersStreamedSpeech(connection) {
    if (!connection) {
        return true;
    }
    if (connection.saveData === true) {
        return false;
    }

    return !['slow-2g', '2g', '3g'].includes(connection.effectiveType);
}
