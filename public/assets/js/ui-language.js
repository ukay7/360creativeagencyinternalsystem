(function () {
    'use strict';
    var host = document.getElementById('ui-language-data');
    if (!host) return;
    var payload = JSON.parse(host.textContent || '{}');
    var dictionary = payload.translations || {};
    if (!dictionary || payload.locale === 'en') return;

    function translated(value) {
        var source = String(value || '').trim();
        return source && dictionary[source] ? dictionary[source] : null;
    }
    function replaceTextNode(node) {
        if (!node.nodeValue || !node.nodeValue.trim()) return;
        var result = translated(node.nodeValue);
        if (!result) return;
        var leading = (node.nodeValue.match(/^\s*/) || [''])[0];
        var trailing = (node.nodeValue.match(/\s*$/) || [''])[0];
        node.nodeValue = leading + result + trailing;
    }
    function translateElement(element) {
        if (!element || element.closest('[data-ui-no-translate],script,style,code,pre')) return;
        Array.prototype.forEach.call(element.childNodes || [], function (node) {
            if (node.nodeType === Node.TEXT_NODE) replaceTextNode(node);
        });
        ['placeholder', 'title', 'aria-label'].forEach(function (attribute) {
            if (!element.hasAttribute || !element.hasAttribute(attribute)) return;
            var result = translated(element.getAttribute(attribute));
            if (result) element.setAttribute(attribute, result);
        });
    }
    document.querySelectorAll('body *').forEach(translateElement);
    document.dispatchEvent(new CustomEvent('agency:language-ready', {detail:payload}));
})();
