/**
 * The assistant's voice: recording a spoken question and reading replies
 * aloud. Loaded on the first use of a voice control, so it never weighs on a
 * page that does not talk.
 *
 * Recording uses MediaRecorder (Opus in WebM, or AAC in MP4 on Safari) and
 * stops by itself when the visitor stops speaking (endpoint detection on the
 * microphone level). The microphone is open only while listening.
 *
 * Replies are read by the server's voice, streamed as raw PCM that plays
 * while it downloads, segment after segment on one Web Audio timeline (so
 * playback started by a tap keeps working after the network round trips,
 * and its level drives the host's mouth). When the server cannot speak
 * (switched off, over a limit, an outage) the browser's own voice reads.
 *
 * The pure helpers at the top have no DOM or audio dependencies and are unit
 * tested (tests/js/chatbot.test.js).
 */

import { createPcm16Decoder, PCM_RATE, prefersStreamedSpeech } from './chatAudio.js';

/* ── Pure helpers ──────────────────────────────────────────────────────── */

/** Recording formats in order of preference; OpenAI reads all of them. */
export const RECORDER_TYPES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/ogg;codecs=opus'];

export function pickRecorderType(isTypeSupported) {
    if (typeof isTypeSupported !== 'function') {
        return '';
    }

    return RECORDER_TYPES.find((type) => {
        try {
            return isTypeSupported(type);
        } catch {
            return false;
        }
    }) ?? '';
}

/** Root mean square of time-domain samples (-1..1): the loudness, 0..1. */
export function levelFromSamples(samples) {
    if (!samples?.length) {
        return 0;
    }
    let sum = 0;
    for (let index = 0; index < samples.length; index++) {
        sum += samples[index] * samples[index];
    }

    return Math.min(1, Math.sqrt(sum / samples.length));
}

/**
 * Decides when a spoken question has ended, from the microphone level over
 * time. The first moments set the room's noise floor; speech is a level well
 * above it for a little while; the end is a pause of `silenceMs` after
 * speech. Returns 'waiting', 'speaking', 'done' or 'no-speech' on each update.
 */
export function createEndpointDetector({
    calibrationMs = 300,
    speechMs = 160,
    silenceMs = 1300,
    noSpeechMs = 7000,
    maxMs = 60000,
} = {}) {
    let start = null;
    let floor = 0.006;
    let floorSamples = 0;
    let voicedMs = 0;
    let heard = false;
    let quietSince = null;
    let last = null;

    return {
        get heardSpeech() {
            return heard;
        },
        update(level, now) {
            if (start === null) {
                start = now;
                last = now;
            }
            const elapsed = now - start;
            const step = Math.max(0, Math.min(200, now - last));
            last = now;

            if (elapsed < calibrationMs) {
                floor = (floor * floorSamples + level) / (floorSamples + 1);
                floorSamples++;

                return 'waiting';
            }

            const speechThreshold = Math.max(0.02, floor * 3);
            const silenceThreshold = Math.max(0.012, floor * 1.8);

            if (elapsed >= maxMs) {
                return heard ? 'done' : 'no-speech';
            }

            if (!heard) {
                if (level > speechThreshold) {
                    voicedMs += step;
                    if (voicedMs >= speechMs) {
                        heard = true;
                        quietSince = null;
                    }
                } else {
                    voicedMs = Math.max(0, voicedMs - step / 2);
                    // A slowly changing room (a fan starts) moves the floor.
                    floor = floor * 0.98 + Math.min(level, speechThreshold) * 0.02;
                }

                if (!heard && elapsed >= noSpeechMs) {
                    return 'no-speech';
                }

                return heard ? 'speaking' : 'waiting';
            }

            if (level < silenceThreshold) {
                quietSince ??= now;
                if (now - quietSince >= silenceMs) {
                    return 'done';
                }
            } else {
                quietSince = null;
            }

            return 'speaking';
        },
    };
}

/**
 * The best installed voice for a language: one for that language, preferring
 * natural (neural, online) voices and the browser's default.
 */
export function pickDeviceVoice(voices, language) {
    const wanted = language === 'ar' ? 'ar' : 'en';
    const matching = (voices ?? []).filter((voice) => typeof voice?.lang === 'string' && voice.lang.toLowerCase().replace('_', '-').startsWith(wanted));
    if (!matching.length) {
        return null;
    }

    const score = (voice) => {
        let points = 0;
        if (/natural|neural|online|premium|enhanced|google/iu.test(voice.name ?? '')) {
            points += 4;
        }
        if (voice.default) {
            points += 2;
        }
        if (wanted === 'en' && /en-(us|gb)/iu.test(voice.lang)) {
            points += 1;
        }
        if (wanted === 'ar' && /ar-(sy|lb|jo|sa|eg)/iu.test(voice.lang)) {
            points += 1;
        }

        return points;
    };

    return [...matching].sort((a, b) => score(b) - score(a))[0];
}

/** Whether a text is mostly Arabic (it picks the voice's language). */
export function isArabicText(text) {
    const arabic = (String(text).match(/[؀-ۿݐ-ݿﭐ-﷿ﹰ-﻿]/gu) ?? []).length;
    const latin = (String(text).match(/[A-Za-z]/gu) ?? []).length;

    return arabic > 0 && arabic >= latin;
}

/**
 * Short sentences for the browser's voice: some engines stop reading a long
 * utterance after about fifteen seconds.
 */
export function deviceChunks(text, limit = 200) {
    const sentences = String(text).replace(/\s+/gu, ' ').trim().split(/(?<=[.!?؟…])\s+/u).filter(Boolean);
    const chunks = [];
    for (const sentence of sentences) {
        if (sentence.length <= limit) {
            chunks.push(sentence);
            continue;
        }
        let current = '';
        for (const word of sentence.split(' ')) {
            if (current && current.length + 1 + word.length > limit) {
                chunks.push(current);
                current = word;
            } else {
                current = current ? `${current} ${word}` : word;
            }
        }
        if (current) {
            chunks.push(current);
        }
    }

    return chunks;
}

/* ── Recording ─────────────────────────────────────────────────────────── */

/**
 * One spoken question. `record()` opens the microphone, resolves with
 * { blob, mimeType, durationMs, transcript } when the visitor stops speaking
 * (or `finish()` is called), or with null when nothing was said or it was
 * cancelled. It rejects with the getUserMedia error (NotAllowedError, ...).
 *
 * With a live transcriber the same audio also streams to OpenAI while it is
 * recorded; `transcript` is then a promise of its text (null when it does not
 * come), and the blob is the fallback to upload.
 */
export class VoiceRecorder {
    constructor({ audioContext, live = null, maxSeconds = 60, noSpeechMs = 7000, silenceMs = 900, onLevel = () => {}, onSpeech = () => {} }) {
        this.audioContext = audioContext;
        this.live = live;
        this.maxMs = Math.max(5, maxSeconds) * 1000;
        this.noSpeechMs = noSpeechMs;
        this.silenceMs = silenceMs;
        this.onLevel = onLevel;
        this.onSpeech = onSpeech;
        this.settle = null;
        this.cancelled = false;
    }

    async record() {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
        });
        this.stream = stream;

        if (this.cancelled) {
            this.release();

            return null;
        }

        const mimeType = pickRecorderType((type) => window.MediaRecorder.isTypeSupported(type));
        const recorder = new MediaRecorder(stream, mimeType ? { mimeType, audioBitsPerSecond: 32000 } : undefined);
        const chunks = [];
        recorder.addEventListener('dataavailable', (event) => {
            if (event.data?.size) {
                chunks.push(event.data);
            }
        });

        const source = this.audioContext.createMediaStreamSource(stream);
        const analyser = this.audioContext.createAnalyser();
        analyser.fftSize = 1024;
        source.connect(analyser);
        const samples = new Float32Array(analyser.fftSize);
        const detector = createEndpointDetector({ noSpeechMs: this.noSpeechMs, maxMs: this.maxMs, silenceMs: this.silenceMs });
        const streaming = this.live ? await this.live.attach(source, this.audioContext) : false;

        const startedAt = performance.now();
        recorder.start(250);

        return new Promise((resolve) => {
            let outcome = 'cancel';
            let spoke = false;
            let transcript = null;

            const finish = (reason) => {
                if (this.settle === null) {
                    return;
                }
                this.settle = null;
                outcome = reason;
                window.clearInterval(timer);
                // The live turn ends with the speech; its text is on the way
                // while the recorder writes its last chunk.
                if (streaming) {
                    if (reason === 'done' && (detector.heardSpeech || spoke)) {
                        transcript = this.live.commit();
                    } else {
                        this.live.discard();
                    }
                }
                source.disconnect();
                if (recorder.state !== 'inactive') {
                    recorder.stop();
                } else {
                    done();
                }
            };

            const done = () => {
                this.release();
                const durationMs = Math.round(performance.now() - startedAt);
                const blob = new Blob(chunks, { type: (recorder.mimeType || mimeType || 'audio/webm').split(';')[0] });
                resolve(outcome === 'done' && (detector.heardSpeech || spoke) && blob.size > 0
                    ? { blob, mimeType: blob.type, durationMs, transcript }
                    : null);
            };

            recorder.addEventListener('stop', done, { once: true });
            this.settle = finish;

            const timer = window.setInterval(() => {
                analyser.getFloatTimeDomainData(samples);
                const level = levelFromSamples(samples);
                this.onLevel(Math.min(1, level * 6));
                const state = detector.update(level, performance.now());
                if (state === 'speaking' && !spoke) {
                    spoke = true;
                    this.onSpeech();
                }
                if (state === 'done' || state === 'no-speech') {
                    finish(state === 'done' ? 'done' : 'silence');
                }
            }, 50);
        });
    }

    /** Stop now and send what was said. */
    finish() {
        this.settle?.('done');
    }

    /** Stop now and send nothing. */
    cancel() {
        this.cancelled = true;
        if (this.settle) {
            this.settle('cancel');
        } else {
            this.release();
        }
    }

    release() {
        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;
    }
}

/* ── Reading aloud ─────────────────────────────────────────────────────── */

// Audio is scheduled a moment ahead, so network jitter does not click.
const LEAD_SECONDS = 0.08;
// Streamed PCM is grouped into pieces of at least this many samples (0.1 s).
const MIN_PIECE_SAMPLES = 2400;

/**
 * Reads replies aloud. `play()` resolves when the reply has been read (or
 * was stopped); only one reply plays at a time.
 *
 * The server's voice comes as segments; each is asked for as raw PCM that
 * plays while it downloads (or one mp3 on a slow connection), the first two
 * together and two in flight. Every piece is scheduled on one Web Audio
 * timeline right after the one before, so segments join without a gap.
 */
export class SpeechPlayer {
    constructor({ audioContext, endpoint, serverSpeech, csrfToken, locale, onLevel = () => {} }) {
        this.audioContext = audioContext;
        this.endpoint = endpoint;
        this.serverSpeech = Boolean(serverSpeech && endpoint && audioContext);
        this.csrfToken = csrfToken;
        this.locale = locale;
        this.onLevel = onLevel;
        this.run = 0;
        this.sources = new Set();
        this.nextTime = 0;
    }

    /** Whether replies can be read aloud here at all. */
    get available() {
        return this.serverSpeech || 'speechSynthesis' in window;
    }

    /**
     * `onStart` runs once, when the first sound actually plays (the wait
     * before it is the server making the audio).
     */
    async play({ text, signature, plainText, onStart = () => {} }) {
        this.stop();
        const run = ++this.run;
        let started = false;
        this.onStart = () => {
            if (!started && run === this.run) {
                started = true;
                onStart();
            }
        };

        if (this.serverSpeech && signature) {
            const outcome = await this.playServer(run, text, signature);
            if (outcome !== 'fallback' || run !== this.run) {
                return;
            }
        }

        await this.playDevice(run, plainText);
    }

    stop() {
        this.run++;
        this.controller?.abort();
        this.controller = null;
        for (const source of this.sources) {
            try {
                source.stop();
            } catch {
                // Not started yet, or already stopped.
            }
        }
        this.sources.clear();
        this.nextTime = 0;
        window.clearInterval(this.levelTimer);
        this.levelTimer = null;
        this.onLevel(0);
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    }

    /**
     * 'done' when every segment played, 'fallback' when the server could not
     * start reading (the browser's voice takes over), 'stopped' otherwise.
     */
    async playServer(run, text, signature) {
        const stream = prefersStreamedSpeech(navigator.connection);
        const pending = new Map();
        // The first two together, then two in flight: the rest of the answer
        // is made while its start plays. (A reply of one segment answers the
        // second request with a cheap 422.)
        const request = (index) => {
            if (!pending.has(index)) {
                const promise = this.openSegment(text, signature, index, stream);
                promise.catch(() => null);
                pending.set(index, promise);
            }

            return pending.get(index);
        };
        request(0);
        request(1);

        let segments = 1;
        for (let index = 0; index < segments; index++) {
            let segment;
            try {
                segment = await request(index);
            } catch {
                segment = null;
            }
            if (run !== this.run) {
                return 'stopped';
            }
            if (!segment) {
                return index === 0 ? 'fallback' : 'stopped';
            }

            segments = segment.segments;
            if (index + 2 < segments) {
                request(index + 2);
            }

            // Schedules this segment's audio as it arrives; the next one is
            // scheduled right behind it.
            for await (const piece of segment.pieces()) {
                if (run !== this.run) {
                    return 'stopped';
                }
                this.schedule(piece);
            }
        }

        await this.drained(run);

        return run === this.run ? 'done' : 'stopped';
    }

    /**
     * One segment's audio: null when the server will not read it; otherwise
     * its pieces (AudioBuffers) as an async iterable. A streamed segment is
     * read from the network straight away (not when its turn comes), so the
     * server is never kept waiting on a full socket.
     */
    async openSegment(text, signature, segment, stream) {
        this.controller ??= new AbortController();
        const response = await fetch(this.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            signal: this.controller.signal,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'audio/L16, audio/mpeg, application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfToken,
            },
            body: JSON.stringify({ text, signature, segment, stream }),
        });
        const type = response.headers.get('Content-Type') ?? '';
        if (!response.ok || type.includes('json')) {
            return null;
        }

        const segments = Number.parseInt(response.headers.get('X-Speech-Segments') ?? '1', 10) || 1;

        if (!type.startsWith('audio/L16') || !response.body) {
            const buffer = await this.audioContext.decodeAudioData(await response.arrayBuffer());

            return {
                segments,
                async *pieces() {
                    yield buffer;
                },
            };
        }

        const queue = [];
        let finished = false;
        let wake = null;
        const notify = () => {
            wake?.();
            wake = null;
        };
        const decode = createPcm16Decoder();
        const reader = response.body.getReader();
        let collected = [];
        let collectedLength = 0;
        const flush = () => {
            if (!collectedLength) {
                return;
            }
            const samples = new Float32Array(collectedLength);
            let offset = 0;
            for (const part of collected) {
                samples.set(part, offset);
                offset += part.length;
            }
            collected = [];
            collectedLength = 0;
            const buffer = this.audioContext.createBuffer(1, samples.length, PCM_RATE);
            buffer.copyToChannel(samples, 0);
            queue.push(buffer);
            notify();
        };

        (async () => {
            try {
                for (;;) {
                    const { value, done } = await reader.read();
                    if (done) {
                        break;
                    }
                    const samples = decode(value);
                    if (samples.length) {
                        collected.push(samples);
                        collectedLength += samples.length;
                        if (collectedLength >= MIN_PIECE_SAMPLES) {
                            flush();
                        }
                    }
                }
            } catch {
                // Stopped or cut off: what arrived still plays.
            }
            flush();
            finished = true;
            notify();
        })();

        return {
            segments,
            async *pieces() {
                for (;;) {
                    if (queue.length) {
                        yield queue.shift();
                    } else if (finished) {
                        return;
                    } else {
                        await new Promise((resolve) => {
                            wake = resolve;
                        });
                    }
                }
            },
        };
    }

    /** Put one piece on the timeline, right after the one before it. */
    schedule(buffer) {
        const context = this.audioContext;
        if (!this.analyser) {
            this.analyser = context.createAnalyser();
            this.analyser.fftSize = 512;
            this.analyser.connect(context.destination);
            this.samples = new Float32Array(this.analyser.fftSize);
        }

        const source = context.createBufferSource();
        source.buffer = buffer;
        source.connect(this.analyser);
        // After a stall (the network fell behind), start again just ahead.
        const startAt = Math.max(this.nextTime, context.currentTime + LEAD_SECONDS);
        source.start(startAt);
        this.nextTime = startAt + buffer.duration;
        this.sources.add(source);
        source.addEventListener('ended', () => {
            this.sources.delete(source);
            source.disconnect();
        }, { once: true });

        const run = this.run;
        window.setTimeout(() => {
            if (run === this.run) {
                this.onStart?.();
            }
        }, Math.max(0, (startAt - context.currentTime) * 1000));

        this.levelTimer ??= window.setInterval(() => {
            this.analyser.getFloatTimeDomainData(this.samples);
            this.onLevel(Math.min(1, levelFromSamples(this.samples) * 4));
        }, 45);
    }

    /** Resolves once everything scheduled has played (or it was stopped). */
    drained(run) {
        return new Promise((resolve) => {
            const check = () => {
                if (run !== this.run || this.audioContext.currentTime >= this.nextTime - 0.01) {
                    window.clearInterval(this.levelTimer);
                    this.levelTimer = null;
                    this.onLevel(0);
                    resolve();

                    return;
                }
                window.setTimeout(check, Math.min(250, Math.max(20, (this.nextTime - this.audioContext.currentTime) * 1000)));
            };
            check();
        });
    }

    async playDevice(run, plainText) {
        if (!('speechSynthesis' in window) || !plainText) {
            return;
        }

        const synth = window.speechSynthesis;
        const language = isArabicText(plainText) ? 'ar' : 'en';
        const voice = pickDeviceVoice(await deviceVoices(), language);
        if (run !== this.run) {
            return;
        }

        for (const chunk of deviceChunks(plainText)) {
            await new Promise((resolve) => {
                const utterance = new SpeechSynthesisUtterance(chunk);
                utterance.lang = voice?.lang ?? (language === 'ar' ? 'ar-SA' : 'en-US');
                if (voice) {
                    utterance.voice = voice;
                }
                utterance.rate = 1;
                // Word boundaries make the mouth move without audio data.
                utterance.addEventListener('boundary', () => {
                    this.onLevel(0.7);
                    window.setTimeout(() => this.onLevel(0.15), 120);
                });
                utterance.addEventListener('start', () => this.onStart?.());
                utterance.addEventListener('end', () => resolve());
                utterance.addEventListener('error', () => resolve());
                synth.speak(utterance);
            });
            if (run !== this.run) {
                return;
            }
        }
        this.onLevel(0);
    }
}

/** The browser's voices, waiting briefly for engines that load them late. */
function deviceVoices() {
    const synth = window.speechSynthesis;
    const voices = synth.getVoices();
    if (voices.length) {
        return Promise.resolve(voices);
    }

    return new Promise((resolve) => {
        const timer = window.setTimeout(() => resolve(synth.getVoices()), 800);
        synth.addEventListener('voiceschanged', () => {
            window.clearTimeout(timer);
            resolve(synth.getVoices());
        }, { once: true });
    });
}
