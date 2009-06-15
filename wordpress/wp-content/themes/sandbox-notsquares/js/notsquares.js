
var hsv1;
var hsv2;

$(document).ready(function(){
  randomBG();
});

function randomBG(){
  if(!hsv1){ 
    hsv1 = rgb2hsv([240,255,13]); 
  }else{
    hsv1 = hsv2;
  }
  var distance = (1 + Math.random())/2;
  var sign = (Math.round(Math.random())*2) - 1;
  var hue = (hsv1[0] + ((sign*distance*60))+360) % 360;
  hsv2 = [hue, hsv1[1], hsv1[2]]
  //console.log("Distance %n, Sign %n, hue %n", distance, sign, hue); 
  animateHSV("#bd", hsv1, hsv2, 10000*distance, randomBG);
}

function maxMinIdx(arr){
  var max;
  var maxIdx;
  var min;
  var minIdx;
  arr.forEach(function(v, i, arr){
    if(!max || v > max ){ max = v; maxIdx = i; }
    if(!min || v < min ){ min = v; minIdx = i; }
  });
  return {max : max, maxIdx : maxIdx, min : min, minIdx : minIdx};
}

function rgb2hsv(rgb){
  rgb = rgb.map(function(c){ return c/255 });
  var m = maxMinIdx(rgb);
  var v = m.max;
  var s = m.max == 0 ? 0 : (1 - (m.min/m.max) );
  if(m.max == m.min){
    var h = 0;
  }else if(m.maxIdx == 0){
    var h = ((60 * (rgb[1] - rgb[2])/(m.max - m.min)) + 360) % 360;
  }else if(m.maxIdx == 1){
    var h = ((60 * (rgb[2] - rgb[0])/(m.max - m.min)) + 120) % 360;
  }else if(m.maxIdx == 2){
    var h = ((60 * (rgb[0] - rgb[1])/(m.max - m.min)) + 240) % 360;
  }
  return [h,s,v];
}

function hsv2rgb(hsv) {
	var r, g, b;
	var i;
	var f, p, q, t;
	
	h = hsv[0]
	s = hsv[1]
	v = hsv[2]
	
	if(s == 0) {
		// Achromatic (grey)
		r = g = b = v;
		return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
	}
	
	h /= 60; // sector 0 to 5
	i = Math.floor(h);
	f = h - i; // factorial part of h
	p = v * (1 - s);
	q = v * (1 - s * f);
	t = v * (1 - s * (1 - f));

	switch(i) {
		case 0:
			r = v;
			g = t;
			b = p;
			break;
			
		case 1:
			r = q;
			g = v;
			b = p;
			break;
			
		case 2:
			r = p;
			g = v;
			b = t;
			break;
			
		case 3:
			r = p;
			g = q;
			b = v;
			break;
			
		case 4:
			r = t;
			g = p;
			b = v;
			break;
			
		default: // case 5:
			r = v;
			g = p;
			b = q;
	}
	
	return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
}

function gradient(start, end, position){
  if(position > 1){ return end; }
  
  return start.map(function(v, idx){
    return start[idx] + ( (end[idx]-start[idx])*position);
  });
   
}

function animateHSV(selector, hsv1, hsv2, duration, callback){
  if(!duration){ duration = 10000 };
  var startTime = new Date();

  var timer = window.setInterval(function(){
    var elapsed = new Date() - startTime;
    var color = gradient(hsv1, hsv2, elapsed/duration);
    var rgb = hsv2rgb(color);
//    console.log("HSV %o, RGB %o", color, rgb);
    $(selector).css('background-color', "rgb("+rgb[0]+","+rgb[1]+","+rgb[2]+")");
    if(elapsed>duration){
      window.clearInterval(timer);
      if(callback){ callback(); };
    }
  }, 100);
}

