/**
 * Hands the microphone's samples to the page in batches of about 40 ms (at
 * 48 kHz), off the main thread's audio clock. Resampling and encoding happen
 * on the page (chatAudio.js), so this stays a few lines.
 */
class SpuPcmCapture extends AudioWorkletProcessor {
    constructor() {
        super();
        this.batch = new Float32Array(2048);
        this.length = 0;
    }

    process(inputs) {
        const channel = inputs[0]?.[0];
        if (channel) {
            for (let index = 0; index < channel.length; index++) {
                this.batch[this.length++] = channel[index];
                if (this.length === this.batch.length) {
                    this.port.postMessage(this.batch.slice(0));
                    this.length = 0;
                }
            }
        }

        return true;
    }
}

registerProcessor('spu-pcm-capture', SpuPcmCapture);
