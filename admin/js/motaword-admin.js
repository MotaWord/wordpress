var languageMap = {
    "af": "af",
    "ar": "ar",
    "az": "az",
    "bel": "be",
    "bg_BG": "bg",
    "bs_BA": "bs",
    "ca": "ca",
    "cs_CZ": "cs",
    "cy": null,
    "da_DK": "da",
    "de": "de",
    "de_CH": "de",
    "de_DE": "de",
    "el": "el",
    "en": "en-US",
    "en_AU": "en-US",
    "en_CA": "en-US",
    "en_GB": "en-US",
    "en_US": "en-US",
    "eo": null,
    "es": "es-ES",
    "es_CL": "es-MX",
    "es_ES": "es-ES",
    "es_MX": "es-MX",
    "es_PE": "es-MX",
    "es_VE": "es-MX",
    "et": "et",
    "eu": null,
    "fa_AF": "fa",
    "fa_IR": "fa",
    "fi": "fi",
    "fo": null,
    "fr_FR": "fr",
    "fr": "fr",
    "fy": null,
    "gd": null,
    "gl_ES": "es-ES",
    "haz": null,
    "he_IL": "he",
    "hi_IN": "hi",
    "hr": "hr",
    "hu_HU": "hu",
    "id_ID": "id",
    "is_IS": "is",
    "it_IT": "it",
    "ja": "ja",
    "jv_ID": null,
    "ka_GE": "ka",
    "kk": null,
    "ko_KR": "ko",
    "ckb": "ku",
    "lo": null,
    "lt_LT": "lt",
    "lv": "lv",
    "mk_MK": "mk",
    "mn": null,
    "ms_MY": "ms",
    "my_MM": "my",
    "nb_NO": "no",
    "ne_NP": "ne-NP",
    "nl_NL": "nl",
    "nn_NO": "no",
    "pl_PL": "pl",
    "pt_BR": "pt-BR",
    "pt_PT": "pt-PT",
    "ro_RO": "ro",
    "ru_RU": "ru",
    "si_LK": null,
    "sk_SK": "sk",
    "sl_SI": "sl",
    "so_SO": null,
    "sq": "sq",
    "sr_RS": "sr",
    "su_ID": null,
    "sv_SE": "sv-SE",
    "ta_LK": "ta",
    "th": "th",
    "tr": "tr",
    "tr_TR": "tr",
    "ug_CN": null,
    "uk": "uk",
    "ur": "ur-PK",
    "uz_UZ": "uz",
    "vec": null,
    "vi": "vi",
    "zh_CN": "zh-CN",
    "zh_HK": "zh-TW",
    "zh_TW": "zh-TW"
};

var motaword = (function (jQuery) {
    'use strict';

    // https://github.com/georgeadamson/jQuery-on-event-binding-polyfill
    (function($) {
        if (!$.fn.on) {

            // Monkeypatch older versions of jQuery to support event binding & delegation using the more convenient .on() method:
            // Can be minimised down to ~160 bytes if you don't need the AMD Module wrapper. :)

            /* jshint laxcomma:true, asi:true, debug:true, curly:false, camelcase:true, browser:true */

            // New syntax: (See http://api.jquery.com/on)
            //   .on( events [, selector ] [, data ], handler(eventObject) )
            // Old syntax:
            //   .bind( events [, data ], handler(eventObject) )
            //   .live( events [, data ], handler(eventObject) )
            //   .delegate( selector, events [, data], handler(eventObject) )

            // Tip: If you need AMD Module support, wrap this script inside the following syntax:
            // ;(function (factory) {
            //   // Register as an anonymous AMD module if relevant, otherwise assume oldskool browser globals:
            //   if (typeof define === "function" && define.amd)
            //     define(["jquery"], factory);
            //   else
            //     factory(jQuery);
            // })(function( $ ) {
            //
            //  ...script goes here...
            //
            // });


            $.fn.on = function(events, selector, data, handler) {

                var self = this;
                var args = arguments.length;

                // .on(events, selector, data, handler)
                if ( args > 3) {
                    return self.delegate(selector, events, data, handler);
                }

                else if (args > 2) {
                    // .on(events, selector, handler)
                    if (typeof selector === 'string') {
                        // handler = data
                        return self.delegate(selector, events, data);
                    }
                    // .on(events, data, handler)
                    else {
                        // handler = data
                        // data    = selector
                        return self.bind(events, selector, data);
                    }
                }

                // .on(events, handler)
                else {
                    // handler = selector
                    return self.bind(events, selector);
                }
            }
        }

        if (!$.fn.off) {
            $.fn.off = function (events, selector, handler) {

                var self = this;
                var args = arguments.length;

                // .off(events, selector)
                if (typeof selector === 'string') {
                    // handler = data
                    if (args > 2) {
                        return self.undelegate(selector, events, handler);
                    } else if (args > 1) {
                        return self.undelegate(selector, events);
                    } else {
                        return self.undelegate();
                    }
                }
                // .off(events)
                else {
                    if (args > 1) {
                        handler = selector;
                        return self.unbind(events, handler);
                    } else if (args > 0) {
                        return self.unbind(events);
                    } else {
                        return self.unbind();
                    }
                }
            };
        }

    })(jQuery);

    jQuery(window).on('tb_unload', function () {
        location.reload();
    });

    jQuery(document).ready(function () {
        var body = jQuery('body');
        var languageSelector = jQuery('#post_lang_choice');
        motaword_updateQuoteInformation();
        var initialSourceLanguage = languageSelector.val();

        languageSelector.on('change', function () {
            var source;

            if (jQuery(this).val() !== initialSourceLanguage && !!(source = motaword_mapPolylangLanguages(jQuery(this).val()))) {
                jQuery('#mw_source_language').val(source);
            }
        });

        jQuery('#mw_source_language, #mw_target_language').on('change', motaword_updateQuoteInformation);

        //submit_quote
        if (false && !!motaword_getQueryVariable("from_post") && !!motaword_getQueryVariable("new_lang")) {
            //pllMainPostTitle
            var pllMainPostTitle = document.getElementById('pllMainPostTitle').value;
            var pllPostTitle = pllMainPostTitle;

            if (pllMainPostTitle == "" || pllMainPostTitle == null)
                pllPostTitle = 'Translating...';
            else
                pllPostTitle = pllMainPostTitle + ' (translating...)';

            document.getElementById('title').value = pllPostTitle;
            jQuery('#save-post').trigger('click');

            return false;
        }

        jQuery('#mw_start_link').on('click', motaword_open_quote_popup);
    });

    function motaword_open_quote_popup(e) {
        e.preventDefault();

        tb_show('MotaWord', jQuery('#mw_start_link').attr('href'));
        jQuery('body').on('submit', '#mw_quote_form', motaword_submitQuote);

        return false;
    }

    function motaword_updateQuoteInformation() {
        var source, target, newUrl, params, wpPostId, pllMainPostID;

        pllMainPostID = jQuery('#pllMainPostID').val();
        source = jQuery('#mw_source_language').val();
        target = jQuery('#mw_target_language').val();
        wpPostId = jQuery('#post_ids').val();

        if (!!pllMainPostID && !!target) {
            var pllTarget = document.getElementById('post_lang_choice').value;
            target = motaword_mapPolylangLanguages(pllTarget);
        }

        params = jQuery.param({
            source_language: source,
            target_language: target,
            post_ids: [wpPostId]
        });
        newUrl = ajaxurl + '?action=mw_get_quote&pllMainPostID=' + pllMainPostID + '&' + params;

        // This link is triggered via thickbox binds.
        jQuery('#mw_start_link').attr('href', newUrl);
    }

    function motaword_submitQuote(e) {
        e.preventDefault();

        jQuery.post(ajaxurl, jQuery('#mw_quote_form').serialize(), function (result) {
            jQuery('#TB_ajaxContent').html(result);
            jQuery('#motaword_box').find('.inside').html('<div class="mw_warning">Refresh the page to get latest translation updates.</div>');
            jQuery('body').on('submit', '#mw_quote_form', motaword_submitQuote);
        });

        return false;
    }

    function motaword_getQueryVariable(variable) {
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split("=");
            if (pair[0] == variable) {
                return decodeURI(pair[1]);
            }
        }
        return false;
    }

    function motaword_mapPolylangLanguages(code) {
        if (languageMap.hasOwnProperty(code)) {
            return languageMap[code];
        }

        return null;
    }

    function motaword_initBulk() {
        jQuery('<option>').val('motaword_bulk').text('Send to MotaWord').appendTo("select[name='action']");
        jQuery('#doaction').on('click', motaword_bulkProcess);
    }

    function motaword_bulkProcess(e) {
        var el = jQuery('#posts-filter').find('.actions').first().find('select');

        if (el.val() !== 'motaword_bulk') {
            return;
        }

        e.preventDefault();

        var link = ajaxurl + '?action=mw_prepare_bulk_quote';
        var postIDsString = '', posts = jQuery('[name="post[]"]:checked');

        jQuery.each(posts, function (i, post) {
                postIDsString = postIDsString + '&post_ids[]=' + jQuery(post).val();
            }
        );

        tb_show('MotaWord', link + postIDsString);
        jQuery('body').on('submit', '#mw_quote_form', motaword_submitQuote);

        return false;
    }

    return {
        init_bulk: motaword_initBulk
    };
})(jQuery);