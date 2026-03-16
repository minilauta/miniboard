import en from './lang/en';
import fi from './lang/fi';
import hu from './lang/hu';
import pl from './lang/pl';
import storage from './storage';

const languages = {
  en,
  fi,
  hu,
  pl,
};

const current_lang = storage.get_cookie('lang') || 'en';
const translations = languages[current_lang] || languages['en'];

function t(key, params) {
  let text = translations[key] !== undefined ? translations[key] : key;

  if (params) {
    for (const name in params) {
      text = text.replace(':' + name, params[name]);
    }
  }

  return text;
}

const available_languages = Object.entries(languages).map(([code, lang]) => ({
  value: code,
  label: lang['_name'] || code,
}));

export default t;
export { available_languages, current_lang };
