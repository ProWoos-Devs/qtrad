/* qTrad codec. PHP and JavaScript share tests/fixtures/codec.json. */
(function (root, factory) {
  var codec = factory();
  if (typeof module === "object" && module.exports) module.exports = codec;
  else root.qtradCodec = codec;
})(typeof window === "undefined" ? globalThis : window, function () {
  "use strict";
  var marker = /(<!--:[a-z]{2,3}-->|<!--:-->|\[:[a-z]{2,3}\]|\[:\]|\{:[a-z]{2,3}\}|\{:\})/i;
  var open = /^(?:<!--:|\[:|\{:)([a-z]{2,3})(?:-->|\]|\})$/i;
  function hasTags(text) {
    return /(<!--:[a-z]{2,3}-->|\[:[a-z]{2,3}\]|\{:[a-z]{2,3}\})/i.test(text || "");
  }
  function detect(text) {
    if (/<!--:[a-z]{2,3}-->/i.test(text || "")) return "comment";
    if (/\{:[a-z]{2,3}\}/i.test(text || "")) return "swirly";
    if (/\[:[a-z]{2,3}\]/i.test(text || "")) return "bracket";
    return null;
  }
  function split(text, enabled, trim) {
    var texts = Object.create(null), current = null;
    enabled.forEach(function (lang) { texts[lang] = ""; });
    var parts = String(text || "").split(marker).filter(function (part) { return part !== ""; });
    parts.forEach(function (part) { var match = open.exec(part); if (match) texts[match[1].toLowerCase()] = ""; });
    parts.forEach(function (part) {
      var match = open.exec(part);
      if (match) { current = match[1].toLowerCase(); return; }
      if (part === "[:]" || part === "{:}" || part.toLowerCase() === "<!--:-->") { current = null; return; }
      if (current) {
        texts[current] = (texts[current] || "") + part;
        current = null;
      } else Object.keys(texts).forEach(function (lang) { texts[lang] += part; });
    });
    if (trim) Object.keys(texts).forEach(function (lang) { texts[lang] = texts[lang].trim(); });
    return texts;
  }
  function keys(texts, order) {
    return (order || []).filter(function (lang) { return Object.prototype.hasOwnProperty.call(texts, lang); })
      .concat(Object.keys(texts).filter(function (lang) { return !order || order.indexOf(lang) < 0; }));
  }
  function join(texts, format, order, force) {
    var langs = keys(texts, order), values = langs.map(function (lang) { return texts[lang] || ""; });
    if (!force && values.every(function (value) { return value === values[0]; })) return values[0] || "";
    var out = "";
    langs.forEach(function (lang) {
      var value = texts[lang] || "";
      if (value === "") return;
      if (format === "comment") out += "<!--:" + lang + "-->" + value + "<!--:-->";
      else if (format === "swirly") out += "{:" + lang + "}" + value;
      else out += "[:" + lang + "]" + value;
    });
    if (out && format === "swirly") out += "{:}";
    if (out && format === "bracket") out += "[:]";
    return out;
  }
  function joinContent(texts, format, order, force) {
    var langs = keys(texts, order), chunks = Object.create(null), max = 1;
    langs.forEach(function (lang) {
      chunks[lang] = String(texts[lang] || "").split(/<!--more-->/i);
      max = Math.max(max, chunks[lang].length);
    });
    var out = "";
    for (var i = 0; i < max; i++) {
      if (i) out += "<!--more-->";
      var slice = Object.create(null);
      langs.forEach(function (lang) { slice[lang] = chunks[lang][i] || ""; });
      out += join(slice, format, langs, force);
    }
    return out;
  }
  return { hasTags: hasTags, detect: detect, split: split, join: join, joinContent: joinContent };
});
