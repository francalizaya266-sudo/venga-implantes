/*
 * Los avisos de los campos, en español. Si se deja vacío un campo obligatorio o se escribe algo que no va, el navegador
 * lo dice en su propio idioma: en una computadora en inglés salía "Please fill out this field". Aquí el panel pone su
 * mensaje en cada caso. Vale para todas las pantallas y ventanas, también las de entrar. Se carga en el <head> del panel.
 */
(() => {
    const isField = (element) => element instanceof HTMLInputElement
        || element instanceof HTMLTextAreaElement
        || element instanceof HTMLSelectElement;

    const isDate = (field) => ['date', 'datetime-local', 'month', 'week'].includes(field.type);

    /* "2026-11-23" (y "2026-11-23T09:00") como se lee en el panel: 23/11/2026. */
    const day = (value) => {
        const [date] = String(value).split('T');
        const [year, month, dayOfMonth] = date.split('-');

        return dayOfMonth ? `${dayOfMonth}/${month}/${year}` : value;
    };

    const decimals = (step) => (String(step).split('.')[1] || '').length;

    const message = (field) => {
        const validity = field.validity;

        if (validity.valueMissing) {
            if (field.type === 'checkbox') return 'Marca esta casilla para seguir.';
            if (field.type === 'radio') return 'Elige una de las opciones.';
            if (field.type === 'file') return 'Elige un archivo.';
            if (field instanceof HTMLSelectElement) return 'Elige una opción de la lista.';

            return 'Completa este campo.';
        }

        if (validity.typeMismatch) {
            if (field.type === 'email') return 'Escribe un correo completo, por ejemplo nombre@gmail.com.';
            if (field.type === 'url') return 'Escribe la dirección completa, con https:// al inicio.';

            return 'Revisa lo que escribiste.';
        }

        if (validity.badInput) {
            if (field.type === 'number') return 'Escribe solo números.';
            if (field.type === 'time') return 'Completa la hora.';

            return 'Completa la fecha.';
        }

        if (validity.rangeUnderflow) return isDate(field) ? `La fecha no puede ser antes del ${day(field.min)}.` : `Pon ${field.min} o más.`;
        if (validity.rangeOverflow) return isDate(field) ? `La fecha no puede ser después del ${day(field.max)}.` : `Pon ${field.max} o menos.`;

        if (validity.stepMismatch) {
            if (field.type !== 'number') return 'Revisa lo que escribiste.';

            return Number(field.step) < 1 ? `Usa como máximo ${decimals(field.step)} decimales.` : `Pon un múltiplo de ${field.step}.`;
        }

        if (validity.tooShort) return `Escribe al menos ${field.minLength} caracteres.`;
        if (validity.tooLong) return `Escribe como máximo ${field.maxLength} caracteres.`;
        if (validity.patternMismatch) return field.title || 'Revisa lo que escribiste.';

        return '';
    };

    /*
     * Los campos que llevan un mensaje del panel. Solo esos se corrigen aquí: el aviso que un campo trae de otra parte
     * (por ejemplo, el cargador de archivos cuando un archivo no se pudo subir) se deja como está.
     */
    const marked = new WeakSet();

    /* El mensaje del panel en lugar del del navegador, o ninguno si el campo ya está bien. */
    const mark = (field) => {
        field.setCustomValidity('');

        const text = message(field);

        field.setCustomValidity(text);

        if (text) {
            marked.add(field);
        } else {
            marked.delete(field);
        }
    };

    const unmark = (field) => {
        field.setCustomValidity('');
        marked.delete(field);
    };

    /* Las opciones de una misma pregunta (radio) se corrigen juntas: basta con elegir una. */
    const group = (field) => {
        if (field.type !== 'radio' || ! field.name) {
            return [field];
        }

        const scope = field.form ? [...field.form.elements] : [...document.getElementsByName(field.name)];

        return scope.filter((element) => element.type === 'radio' && element.name === field.name);
    };

    /* Al intentar guardar, el navegador avisa del primer campo con problema: ya con el mensaje del panel. */
    document.addEventListener('invalid', (event) => {
        const field = event.target;

        if (isField(field) && (marked.has(field) || ! field.validity.customError)) {
            mark(field);
        }
    }, true);

    /* Mientras se corrige, el mensaje sigue al campo: cambia si sigue mal y se va cuando queda bien. */
    const follow = (event) => {
        if (isField(event.target)) {
            group(event.target).filter((field) => marked.has(field)).forEach(mark);
        }
    };

    document.addEventListener('input', follow, true);
    document.addEventListener('change', follow, true);

    /*
     * Cada vez que se toca "Guardar" se revisa de nuevo desde cero: un campo que la pantalla llenó sola (sin que la
     * persona escribiera) no queda marcado con el aviso de antes.
     */
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('button, input[type="submit"]') : null;

        if (! button || button.type !== 'submit' || ! button.form) {
            return;
        }

        [...button.form.elements].filter((field) => marked.has(field)).forEach(unmark);
    }, true);
})();
