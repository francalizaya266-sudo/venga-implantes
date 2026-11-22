/*
 * "Dictar": la voz se escribe en el campo con el reconocimiento de voz del navegador. Chrome y Edge mandan el audio
 * a sus servidores (Google, Microsoft) para pasarlo a texto, como el micrófono del teclado; por eso el aviso dice
 * "por internet". Firefox no lo tiene: el botón explica que se use Chrome, Edge o el micrófono del teclado.
 *
 * window.dictation({ field, separator }) se mezcla en un componente de Alpine:
 *   - field(): el campo donde se escribe (un textarea o un input). Con `this` del componente, donde $el es el
 *     botón que se tocó.
 *   - separator: entre lo dictado y lo que ya había. Sin él, como en una nota: espacio y mayúscula al empezar frase.
 * Deja voiceOn (escuchando), voiceText (lo que va entendiendo), voiceProblem, toggleVoice() y stopVoice().
 * Se carga en el <head> del panel, antes que Alpine y que odontogram.js.
 */
(() => {
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const PROBLEMS = {
        'not-allowed': 'El navegador no dejó usar el micrófono. Permítelo en el candado junto a la dirección, o usa el micrófono del teclado.',
        'service-not-allowed': 'El navegador no dejó usar el micrófono. Permítelo en el candado junto a la dirección, o usa el micrófono del teclado.',
        'audio-capture': 'No se encontró un micrófono. Revisa que esté conectado.',
        'network': 'Para dictar se necesita internet. Mientras, usa el micrófono del teclado.',
        'no-speech': 'No se escuchó nada. Toca Dictar y habla cerca del micrófono.',
    };

    // Uno a la vez: empezar a dictar en otro campo para el anterior.
    let active = null;

    const join = (before, text, separator) => {
        if (separator) {
            return before.trim() === '' ? text : (/,\s*$/.test(before) ? (/\s$/.test(before) ? '' : ' ') : separator) + text;
        }

        const sentence = before.trim() === '' || /[.!?:]\s*$/.test(before) || /\n\s*$/.test(before);
        const words = sentence ? text.charAt(0).toUpperCase() + text.slice(1) : text;

        return (before === '' || /\s$/.test(before) ? '' : ' ') + words;
    };

    window.dictation = (options) => {
        let recognition = null;

        return {
            voiceOn: false,
            voiceText: '',
            voiceProblem: '',

            /** Un aviso que se va solo a los 10 segundos, o al volver a tocar Dictar. */
            complainVoice(problem) {
                this.voiceProblem = problem;
                setTimeout(() => {
                    if (this.voiceProblem === problem) {
                        this.voiceProblem = '';
                    }
                }, 10000);
            },

            toggleVoice() {
                if (recognition) {
                    this.stopVoice();

                    return;
                }

                this.voiceProblem = '';

                if (! Recognition) {
                    this.complainVoice('Este navegador no dicta. Usa Chrome o Edge, o el micrófono del teclado (en Windows, Windows + H).');

                    return;
                }

                if (! window.isSecureContext) {
                    this.complainVoice('Para dictar aquí, el sistema tiene que abrirse con https. Mientras, usa el micrófono del teclado.');

                    return;
                }

                // El campo se busca al tocar Dictar: lo que se oye después va ahí aunque se toque otra cosa.
                const field = options.field.call(this);

                if (! field) {
                    return;
                }

                active?.stop();

                const listening = new Recognition();
                listening.lang = 'es-PE';
                listening.continuous = true;
                listening.interimResults = true;

                listening.onresult = (event) => {
                    let partial = '';

                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        const text = event.results[i][0].transcript.trim();

                        if (! event.results[i].isFinal) {
                            partial += ' ' + text;
                        } else if (text) {
                            this.writeVoice(field, text);
                        }
                    }

                    this.voiceText = partial.trim();
                };
                listening.onerror = (event) => {
                    if (event.error !== 'aborted') {
                        this.complainVoice(PROBLEMS[event.error] ?? 'No se pudo dictar. Prueba de nuevo.');
                    }
                };
                listening.onend = () => {
                    if (active === listening) {
                        active = null;
                    }

                    if (recognition === listening) {
                        recognition = null;
                        this.voiceOn = false;
                        this.voiceText = '';
                    }
                };

                try {
                    listening.start();
                    recognition = active = listening;
                    this.voiceOn = true;
                } catch (error) {
                    this.complainVoice('No se pudo dictar. Prueba de nuevo.');
                }
            },

            stopVoice() {
                recognition?.stop();
            },

            writeVoice(field, text) {
                const focused = document.activeElement === field;
                const start = focused ? field.selectionStart : field.value.length;
                const end = focused ? field.selectionEnd : field.value.length;

                field.setRangeText(join(field.value.slice(0, start), text, options.separator), start, end, 'end');
                field.dispatchEvent(new Event('input', { bubbles: true }));
            },
        };
    };
})();
