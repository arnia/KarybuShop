<?php

/**
* Returns a locale from a country code that is provided.
*
* @param $country_code  ISO 3166-2-alpha 2 country code
* @param $language_code ISO 639-1-alpha 2 language code
* @returns  a locale, formatted like en_US, or null if not found
**/
function getLocale ($country_code='', $language_code='') {
    // list-of-all-locales-and-their-short-codes
    $locales = array(
        'af_ZA',
        'am_ET',
        'ar_AE',
        'ar_BH',
        'ar_DZ',
        'ar_EG',
        'ar_IQ',
        'ar_JO',
        'ar_KW',
        'ar_LB',
        'ar_LY',
        'ar_MA',
        'arn-CL',
        'ar_OM',
        'ar_QA',
        'ar_SA',
        'ar_SY',
        'ar_TN',
        'ar_YE',
        'as_IN',
        'az-Cyrl-AZ',
        'az-Latn-AZ',
        'ba_RU',
        'be_BY',
        'bg_BG',
        'bn_BD',
        'bn_IN',
        'bo_CN',
        'br_FR',
        'bs-Cyrl-BA',
        'bs-Latn-BA',
        'ca_ES',
        'co_FR',
        'cs_CZ',
        'cy_GB',
        'da_DK',
        'de_AT',
        'de_CH',
        'de_DE',
        'de_LI',
        'de_LU',
        'dsb-DE',
        'dv_MV',
        'el_GR',
        'en_AU',
        'en_BZ',
        'en_CA',
        'en_GB',
        'en_IE',
        'en_IN',
        'en_JM',
        'en_MY',
        'en_NZ',
        'en_PH',
        'en_SG',
        'en_TT',
        'en_US',
        'es_AR',
        'es_BO',
        'es_CL',
        'es_CO',
        'es_CR',
        'es_DO',
        'es_EC',
        'es_ES',
        'es_GT',
        'es_HN',
        'es_MX',
        'es_NI',
        'es_PA',
        'es_PE',
        'es_PR',
        'es_PY',
        'es_SV',
        'es_US',
        'es_UY',
        'es_VE',
        'et_EE',
        'eu_ES',
        'fa_IR',
        'fi_FI',
        'fil-PH',
        'fo_FO',
        'fr_BE',
        'fr_CA',
        'fr_CH',
        'fr_FR',
        'fr_LU',
        'fr_MC',
        'fy_NL',
        'ga_IE',
        'gd_GB',
        'gl_ES',
        'gsw-FR',
        'gu_IN',
        'ha-Latn-NG',
        'he_IL',
        'hi_IN',
        'hr_BA',
        'hr_HR',
        'hsb-DE',
        'hu_HU',
        'hy_AM',
        'id_ID',
        'ig_NG',
        'ii_CN',
        'is_IS',
        'it_CH',
        'it_IT',
        'iu-Cans-CA',
        'iu-Latn-CA',
        'ja_JP',
        'ka_GE',
        'kk_KZ',
        'kl_GL',
        'km_KH',
        'kn_IN',
        'kok-IN',
        'ko_KR',
        'ky_KG',
        'lb_LU',
        'lo_LA',
        'lt_LT',
        'lv_LV',
        'mi_NZ',
        'mk_MK',
        'ml_IN',
        'mn_MN',
        'mn-Mong-CN',
        'moh-CA',
        'mr_IN',
        'ms_BN',
        'ms_MY',
        'mt_MT',
        'nb_NO',
        'ne_NP',
        'nl_BE',
        'nl_NL',
        'nn_NO',
        'nso-ZA',
        'oc_FR',
        'or_IN',
        'pa_IN',
        'pl_PL',
        'prs-AF',
        'ps_AF',
        'pt_BR',
        'pt_PT',
        'qut-GT',
        'quz-BO',
        'quz-EC',
        'quz-PE',
        'rm_CH',
        'ro_RO',
        'ru_RU',
        'rw_RW',
        'sah-RU',
        'sa_IN',
        'se_FI',
        'se_NO',
        'se_SE',
        'si_LK',
        'sk_SK',
        'sl_SI',
        'sma-NO',
        'sma-SE',
        'smj-NO',
        'smj-SE',
        'smn-FI',
        'sms-FI',
        'sq_AL',
        'sr-Cyrl-BA',
        'sr-Cyrl-CS',
        'sr-Cyrl-ME',
        'sr-Cyrl-RS',
        'sr-Latn-BA',
        'sr-Latn-CS',
        'sr-Latn-ME',
        'sr-Latn-RS',
        'sv_FI',
        'sv_SE',
        'sw_KE',
        'syr-SY',
        'ta_IN',
        'te_IN',
        'tg-Cyrl-TJ',
        'th_TH',
        'tk_TM',
        'tn_ZA',
        'tr_TR',
        'tt_RU',
        'tzm-Latn-DZ',
        'ug_CN',
        'uk_UA',
        'ur_PK',
        'uz-Cyrl-UZ',
        'uz-Latn-UZ',
        'vi_VN',
        'wo_SN',
        'xh_ZA',
        'yo_NG',
        'zh_CN',
        'zh_HK',
        'zh_MO',
        'zh_SG',
        'zh_TW',
        'zu-ZA'
    );

    /*
    foreach ($locales as $locale)
    {
        $locale_region = locale_get_region($locale);
        $locale_language = locale_get_primary_language($locale);
        $locale_array = array('language' => $locale_language,
            'region' => $locale_region);

        if (strtoupper($country_code) == $locale_region &&
            $language_code == '')
        {
            return locale_compose($locale_array);
        }
        elseif (strtoupper($country_code) == $locale_region &&
            strtolower($language_code) == $locale_language)
        {
            return locale_compose($locale_array);
        }
    }
    */

    /*
    $locale = strtolower($language_code).'_'.strtoupper($country_code);
    echo $locale;
    if (in_array($locale, $locales))
        return $locale;
    else
        return null;
    */

    if (isset($language_code)) {
        $ret = '';
        foreach ($locales as $value) {
            if (strtolower(substr($value,0,strlen($language_code))) == strtolower($language_code))
                $ret = $value;
        };
        return $ret;
    }

    return false;

}
