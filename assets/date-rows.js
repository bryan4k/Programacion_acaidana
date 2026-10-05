'use strict';

(function (root) {
    const spanishDays = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const spanishMonths = ['Ene.', 'Feb.', 'Mar.', 'Abr.', 'May.', 'Jun.', 'Jul.', 'Ago.', 'Sept.', 'Oct.', 'Nov.', 'Dic.'];

    function formatDate(value) {
        if (!value) return '';
        const date = new Date(`${value}T12:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        return `${spanishDays[date.getDay()]} ${String(date.getDate()).padStart(2, '0')} ${spanishMonths[date.getMonth()]}`;
    }

    function formatProgramDates(firstDate, secondDate) {
        return [firstDate, secondDate].filter(Boolean).map(formatDate);
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { formatProgramDates };
    else root.ProgramDates = { formatProgramDates };
})(typeof window !== 'undefined' ? window : globalThis);
