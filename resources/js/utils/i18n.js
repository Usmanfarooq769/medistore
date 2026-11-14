import { ref } from 'vue';
import { ur } from './ur';

/**
 * Tiny i18n: English text is the key, so untranslated strings simply stay English.
 *   {{ t('Today sales') }}   ->  "آج کی فروخت" when Urdu is on
 * Add more entries in utils/ur.js to translate more screens.
 */
export const lang = ref(localStorage.getItem('lang') || 'en');
const dict = { ur };

export function t(text) {
    if (lang.value === 'en') return text;
    return dict[lang.value]?.[text] ?? text;
}

export function applyLang(l = lang.value) {
    lang.value = l;
    localStorage.setItem('lang', l);
    const h = document.documentElement;
    h.setAttribute('lang', l);
    h.setAttribute('dir', l === 'ur' ? 'rtl' : 'ltr');
    h.classList.toggle('lang-ur', l === 'ur');
}

export const toggleLang = () => applyLang(lang.value === 'en' ? 'ur' : 'en');
