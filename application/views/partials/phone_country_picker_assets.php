<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Shared assets for the searchable country-code phone picker (the same widget the
 * Booking form uses). Include ONCE per page; then render one or more markup blocks
 * with class "phone-input-wrapper" + attribute data-picker (see the Manual Lead
 * modal and the Customer form). Each wrapper contains:
 *   .phone-country-selector (flag + code + arrow), .phone-input-field (the local
 *   number), input.phone-code-value (hidden, holds the "+60" dial code) and
 *   .phone-dropdown (search + list of .phone-dropdown-item[data-code]).
 * The generic initialiser below wires every wrapper on the page — no per-instance
 * function needed. window.phonePickerSetCode(wrapperEl, code) selects a code
 * programmatically (used for edit pre-fill and modal reset).
 */
?>
<style>
    /* Country-code phone picker (shared; ported from the Booking form) */
    .phone-input-wrapper { position: relative; display: flex; align-items: stretch; border: 1px solid #e4e6ef; border-radius: 0.42rem; background-color: #fff; transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out; }
    .phone-input-wrapper:focus-within { border-color: #5e72e4; box-shadow: 0 0 0 0.2rem rgba(94, 114, 228, 0.25); }
    .phone-input-wrapper.disabled { background-color: #f3f6f9; opacity: 0.6; cursor: not-allowed; }
    .phone-country-selector { position: relative; display: flex; align-items: center; padding: 0.75rem 0.75rem; background-color: #f7f8fa; border-right: 1px solid #e4e6ef; cursor: pointer; min-width: 110px; user-select: none; }
    .phone-country-selector.disabled { cursor: not-allowed; }
    .phone-country-flag { font-size: 1.25rem; margin-right: 0.5rem; line-height: 1; }
    .phone-country-code { font-weight: 500; color: #3f4254; font-size: 0.95rem; margin-right: 0.25rem; }
    .phone-country-arrow { margin-left: auto; color: #7e8299; font-size: 0.75rem; transition: transform 0.2s; }
    .phone-country-selector.open .phone-country-arrow { transform: rotate(180deg); }
    .phone-input-field { flex: 1; border: none; padding: 0.75rem 1rem; font-size: 0.95rem; background: transparent; outline: none; min-width: 0; }
    .phone-input-field:disabled { background-color: transparent; cursor: not-allowed; }
    .phone-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e4e6ef; border-radius: 0.42rem; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15); z-index: 1060; max-height: 300px; overflow-y: auto; display: none; margin-top: 0.25rem; }
    .phone-dropdown.show { display: block; }
    .phone-dropdown-search { padding: 0.75rem; border-bottom: 1px solid #e4e6ef; position: sticky; top: 0; background: #fff; z-index: 1; }
    .phone-dropdown-search input { width: 100%; padding: 0.5rem; border: 1px solid #e4e6ef; border-radius: 0.25rem; font-size: 0.9rem; }
    .phone-dropdown-list { padding: 0.25rem 0; max-height: 250px; overflow-y: auto; }
    .phone-dropdown-item { display: flex; align-items: center; padding: 0.75rem; cursor: pointer; transition: background-color 0.15s; }
    .phone-dropdown-item:hover { background-color: #f7f8fa; }
    .phone-dropdown-item.selected { background-color: #e4e6ef; }
    .phone-dropdown-item-flag { font-size: 1.25rem; margin-right: 0.75rem; line-height: 1; width: 24px; text-align: center; }
    .phone-dropdown-item-name { flex: 1; color: #3f4254; font-size: 0.9rem; }
    .phone-dropdown-item-code { color: #7e8299; font-size: 0.85rem; font-weight: 500; margin-left: 0.5rem; }
</style>
<script>
(function() {
    if (window.phonePickerInitAll) { return; } // include-once guard

    // Country name -> emoji flag (ported from the Booking form's getCountryFlag).
    var FLAGS = {
        'MALAYSIA': '🇲🇾', 'SINGAPORE': '🇸🇬', 'THAILAND': '🇹🇭', 'INDONESIA': '🇮🇩',
        'PHILIPPINES': '🇵🇭', 'VIETNAM': '🇻🇳', 'CAMBODIA': '🇰🇭', 'MYANMAR': '🇲🇲', 'BURMA': '🇲🇲',
        'LAOS': '🇱🇦', 'BRUNEI': '🇧🇳', 'BRUNEI DARUSSALAM': '🇧🇳', 'EAST TIMOR': '🇹🇱', 'TIMOR-LESTE': '🇹🇱',
        'CHINA': '🇨🇳', 'JAPAN': '🇯🇵', 'SOUTH KOREA': '🇰🇷', 'KOREA': '🇰🇷', 'NORTH KOREA': '🇰🇵',
        'HONG KONG': '🇭🇰', 'MACAU': '🇲🇴', 'TAIWAN': '🇹🇼', 'MONGOLIA': '🇲🇳',
        'INDIA': '🇮🇳', 'PAKISTAN': '🇵🇰', 'BANGLADESH': '🇧🇩', 'SRI LANKA': '🇱🇰',
        'NEPAL': '🇳🇵', 'BHUTAN': '🇧🇹', 'MALDIVES': '🇲🇻', 'AFGHANISTAN': '🇦🇫',
        'AUSTRALIA': '🇦🇺', 'NEW ZEALAND': '🇳🇿', 'FIJI': '🇫🇯', 'PAPUA NEW GUINEA': '🇵🇬',
        'NEW CALEDONIA': '🇳🇨', 'FRENCH POLYNESIA': '🇵🇫', 'SAMOA': '🇼🇸', 'TONGA': '🇹🇴',
        'UNITED STATES': '🇺🇸', 'USA': '🇺🇸', 'CANADA': '🇨🇦', 'MEXICO': '🇲🇽',
        'GUATEMALA': '🇬🇹', 'BELIZE': '🇧🇿', 'EL SALVADOR': '🇸🇻', 'HONDURAS': '🇭🇳',
        'NICARAGUA': '🇳🇮', 'COSTA RICA': '🇨🇷', 'PANAMA': '🇵🇦', 'CUBA': '🇨🇺',
        'JAMAICA': '🇯🇲', 'HAITI': '🇭🇹', 'DOMINICAN REPUBLIC': '🇩🇴', 'BAHAMAS': '🇧🇸',
        'BARBADOS': '🇧🇧', 'TRINIDAD AND TOBAGO': '🇹🇹', 'PUERTO RICO': '🇵🇷',
        'BRAZIL': '🇧🇷', 'ARGENTINA': '🇦🇷', 'CHILE': '🇨🇱', 'COLOMBIA': '🇨🇴',
        'PERU': '🇵🇪', 'VENEZUELA': '🇻🇪', 'ECUADOR': '🇪🇨', 'BOLIVIA': '🇧🇴',
        'PARAGUAY': '🇵🇾', 'URUGUAY': '🇺🇾', 'GUYANA': '🇬🇾', 'SURINAME': '🇸🇷',
        'UNITED KINGDOM': '🇬🇧', 'UK': '🇬🇧', 'IRELAND': '🇮🇪', 'FRANCE': '🇫🇷',
        'GERMANY': '🇩🇪', 'ITALY': '🇮🇹', 'SPAIN': '🇪🇸', 'PORTUGAL': '🇵🇹',
        'NETHERLANDS': '🇳🇱', 'BELGIUM': '🇧🇪', 'SWITZERLAND': '🇨🇭', 'AUSTRIA': '🇦🇹',
        'LUXEMBOURG': '🇱🇺', 'MONACO': '🇲🇨', 'LIECHTENSTEIN': '🇱🇮', 'ANDORRA': '🇦🇩',
        'SAN MARINO': '🇸🇲', 'VATICAN CITY': '🇻🇦', 'MALTA': '🇲🇹',
        'SWEDEN': '🇸🇪', 'NORWAY': '🇳🇴', 'DENMARK': '🇩🇰', 'FINLAND': '🇫🇮',
        'ICELAND': '🇮🇸', 'ESTONIA': '🇪🇪', 'LATVIA': '🇱🇻', 'LITHUANIA': '🇱🇹',
        'RUSSIA': '🇷🇺', 'POLAND': '🇵🇱', 'CZECH REPUBLIC': '🇨🇿', 'HUNGARY': '🇭🇺',
        'ROMANIA': '🇷🇴', 'BULGARIA': '🇧🇬', 'CROATIA': '🇭🇷', 'SERBIA': '🇷🇸',
        'SLOVAKIA': '🇸🇰', 'SLOVENIA': '🇸🇮', 'BOSNIA AND HERZEGOVINA': '🇧🇦',
        'MACEDONIA': '🇲🇰', 'ALBANIA': '🇦🇱', 'MONTENEGRO': '🇲🇪', 'KOSOVO': '🇽🇰',
        'BELARUS': '🇧🇾', 'UKRAINE': '🇺🇦', 'MOLDOVA': '🇲🇩', 'GEORGIA': '🇬🇪',
        'ARMENIA': '🇦🇲', 'AZERBAIJAN': '🇦🇿',
        'GREECE': '🇬🇷', 'TURKEY': '🇹🇷', 'CYPRUS': '🇨🇾',
        'SAUDI ARABIA': '🇸🇦', 'UNITED ARAB EMIRATES': '🇦🇪', 'UAE': '🇦🇪',
        'QATAR': '🇶🇦', 'KUWAIT': '🇰🇼', 'BAHRAIN': '🇧🇭', 'OMAN': '🇴🇲',
        'YEMEN': '🇾🇪', 'IRAQ': '🇮🇶', 'IRAN': '🇮🇷', 'ISRAEL': '🇮🇱',
        'PALESTINE': '🇵🇸', 'JORDAN': '🇯🇴', 'LEBANON': '🇱🇧', 'SYRIA': '🇸🇾',
        'EGYPT': '🇪🇬', 'LIBYA': '🇱🇾', 'TUNISIA': '🇹🇳', 'ALGERIA': '🇩🇿',
        'MOROCCO': '🇲🇦', 'SUDAN': '🇸🇩', 'SOUTH SUDAN': '🇸🇸', 'ETHIOPIA': '🇪🇹',
        'SOUTH AFRICA': '🇿🇦', 'KENYA': '🇰🇪', 'TANZANIA': '🇹🇿', 'UGANDA': '🇺🇬',
        'RWANDA': '🇷🇼', 'GHANA': '🇬🇭', 'NIGERIA': '🇳🇬', 'SENEGAL': '🇸🇳',
        'IVORY COAST': '🇨🇮', 'CAMEROON': '🇨🇲', 'GABON': '🇬🇦', 'CONGO': '🇨🇬',
        'ANGOLA': '🇦🇴', 'MOZAMBIQUE': '🇲🇿', 'MADAGASCAR': '🇲🇬', 'MAURITIUS': '🇲🇺',
        'SEYCHELLES': '🇸🇨', 'ZIMBABWE': '🇿🇼', 'BOTSWANA': '🇧🇼', 'NAMIBIA': '🇳🇦',
        'KAZAKHSTAN': '🇰🇿', 'UZBEKISTAN': '🇺🇿', 'TURKMENISTAN': '🇹🇲', 'KYRGYZSTAN': '🇰🇬',
        'TAJIKISTAN': '🇹🇯'
    };

    function flagFor(name) {
        if (!name) { return '🌐'; }
        return FLAGS[('' + name).toUpperCase().trim()] || '🌐';
    }

    // Select a dial code programmatically: set the hidden field + the flag/code
    // display and highlight the matching list item. Pass '' to clear.
    window.phonePickerSetCode = function(wrapper, code) {
        var $w = $(wrapper);
        if (!$w.length) { return; }
        code = (code == null ? '' : ('' + code)).trim();
        var $item = code ? $w.find('.phone-dropdown-item').filter(function() {
            return ('' + ($(this).attr('data-code') || '')) === code;
        }).first() : $();
        $w.find('.phone-code-value').val(code);
        var country = $item.length ? $item.attr('data-country-name') : '';
        $w.find('.phone-country-flag').text(code ? flagFor(country) : '🌐');
        $w.find('.phone-country-code').text(code || '--');
        $w.find('.phone-dropdown-item').removeClass('selected');
        if ($item.length) { $item.addClass('selected'); }
    };

    function initWrapper(wrapper) {
        var $w = $(wrapper);
        if ($w.data('phoneInit')) { return; }
        $w.data('phoneInit', true);

        var $selector = $w.find('.phone-country-selector');
        var $dropdown = $w.find('.phone-dropdown');
        var $search   = $w.find('.phone-search');

        // Paint each list item's flag from its country name.
        $w.find('.phone-dropdown-item').each(function() {
            $(this).find('.phone-dropdown-item-flag').text(flagFor($(this).attr('data-country-name')));
        });

        // Initial display from the hidden field's starting value.
        window.phonePickerSetCode(wrapper, $w.find('.phone-code-value').val());

        $selector.on('click', function(e) {
            if ($selector.hasClass('disabled')) { return; }
            e.stopPropagation();
            $dropdown.toggleClass('show');
            $selector.toggleClass('open', $dropdown.hasClass('show'));
            if ($dropdown.hasClass('show')) { $search.val('').trigger('input').focus(); }
        });

        // The widget can sit inside a submitting form (Manual Lead modal); don't
        // let Enter in the search box submit it.
        $search.on('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); }
        });

        $search.on('input', function() {
            var term = $(this).val().toLowerCase();
            $w.find('.phone-dropdown-item').each(function() {
                var name    = $(this).find('.phone-dropdown-item-name').text().toLowerCase();
                var code    = ('' + ($(this).attr('data-code') || '')).toLowerCase();
                var country = ('' + ($(this).attr('data-country') || '')).toLowerCase();
                $(this).toggle(name.indexOf(term) !== -1 || code.indexOf(term) !== -1 || country.indexOf(term) !== -1);
            });
        });

        $w.on('click', '.phone-dropdown-item', function() {
            window.phonePickerSetCode(wrapper, $(this).attr('data-code'));
            $dropdown.removeClass('show');
            $selector.removeClass('open');
            $search.val('');
            $w.find('.phone-dropdown-item').show();
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest($w).length) {
                $dropdown.removeClass('show');
                $selector.removeClass('open');
            }
        });
    }

    // Wire every picker on the page (and any added later, e.g. re-opened modals).
    window.phonePickerInitAll = function(ctx) {
        $(ctx || document).find('.phone-input-wrapper[data-picker]').each(function() {
            initWrapper(this);
        });
    };

    $(function() { window.phonePickerInitAll(document); });
})();
</script>
