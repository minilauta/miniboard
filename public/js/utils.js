// Source: https://gist.github.com/0x263b/2bdd90886c2036a1ad5bcf06d6e6fb37
String.prototype.toHex = function() {
  var hash = 0;
  if (this.length === 0) return hash;
  for (var i = 0; i < this.length; i++) {
    hash = this.charCodeAt(i) + ((hash << 5) - hash);
    hash = hash & hash;
  }
  var color = '#';
  for (var i = 0; i < 3; i++) {
    var value = (hash >> (i * 8)) & 255;
    color += ('00' + value.toString(16)).substr(-2);
  }
  return color;
}
