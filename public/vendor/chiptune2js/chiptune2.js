// constants
const OPENMPT_MODULE_RENDER_STEREOSEPARATION_PERCENT = 2
const OPENMPT_MODULE_RENDER_INTERPOLATIONFILTER_LENGTH = 3
const CHIPTUNE_POSITION_FRAMES = 256;

// read a libopenmpt allocated string
function ChiptuneJsReadString(ptr) {
  var str = UTF8ToString(ptr);
  libopenmpt._openmpt_free_string(ptr);
  return str;
}

// audio context
var ChiptuneAudioContext = window['AudioContext'] || window['webkitAudioContext'];

// config
var ChiptuneJsConfig = function (repeatCount, stereoSeparation, interpolationFilter, context)
{
  this.repeatCount = repeatCount;
  this.stereoSeparation = stereoSeparation;
  this.interpolationFilter = interpolationFilter;
  this.context = context;
}

ChiptuneJsConfig.prototype.constructor = ChiptuneJsConfig;

// player
var ChiptuneJsPlayer = function (config) {
  this.config = config;
  this.context = config.context || new ChiptuneAudioContext();
  this.gainNode = this.context.createGain();
  this.gainNode.connect(this.context.destination);
  this.currentPlayingNode = null;
  this.handlers = [];
  this.touchLocked = true;
}

ChiptuneJsPlayer.prototype.constructor = ChiptuneJsPlayer;

// event handlers section
ChiptuneJsPlayer.prototype.fireEvent = function (eventName, response) {
  var  handlers = this.handlers;
  if (handlers.length) {
    handlers.forEach(function (handler) {
      if (handler.eventName === eventName) {
        handler.handler(response);
      }
    })
  }
}

ChiptuneJsPlayer.prototype.addHandler = function (eventName, handler) {
  this.handlers.push({eventName: eventName, handler: handler});
}

ChiptuneJsPlayer.prototype.onEnded = function (handler) {
  this.addHandler('onEnded', handler);
}

ChiptuneJsPlayer.prototype.onError = function (handler) {
  this.addHandler('onError', handler);
}

// metadata
ChiptuneJsPlayer.prototype.duration = function() {
  return libopenmpt._openmpt_module_get_duration_seconds(this.currentPlayingNode.modulePtr);
}

ChiptuneJsPlayer.prototype.getCurrentRow = function() {
  return libopenmpt._openmpt_module_get_current_row(this.currentPlayingNode.modulePtr);  
}

ChiptuneJsPlayer.prototype.getCurrentPattern = function() {
  return libopenmpt._openmpt_module_get_current_pattern(this.currentPlayingNode.modulePtr);  
}

ChiptuneJsPlayer.prototype.getCurrentOrder = function() {
  return libopenmpt._openmpt_module_get_current_order(this.currentPlayingNode.modulePtr);  
}

ChiptuneJsPlayer.prototype.getCurrentTime = function () {
  return libopenmpt._openmpt_module_get_position_seconds(this.currentPlayingNode.modulePtr);
};

ChiptuneJsPlayer.prototype.seek = function (position) {
  if (this.currentPlayingNode != null && this.currentPlayingNode.modulePtr != 0) {
    libopenmpt._openmpt_module_set_position_seconds(this.currentPlayingNode.modulePtr, position);
  }
};

ChiptuneJsPlayer.prototype.setVolume = function (volume) {
  this.gainNode.gain.value = volume;
};

ChiptuneJsPlayer.prototype.setStereoSeparation = function (stereoSeparation) {
  this.config.stereoSeparation = stereoSeparation;
  if (this.currentPlayingNode != null && this.currentPlayingNode.modulePtr != 0) {
    libopenmpt._openmpt_module_set_render_param(this.currentPlayingNode.modulePtr, OPENMPT_MODULE_RENDER_STEREOSEPARATION_PERCENT, stereoSeparation);
  }
};

ChiptuneJsPlayer.prototype.getTotalOrder = function () {
  return libopenmpt._openmpt_module_get_num_orders(this.currentPlayingNode.modulePtr);
};

ChiptuneJsPlayer.prototype.getTotalPatterns = function () {
  return libopenmpt._openmpt_module_get_num_patterns(this.currentPlayingNode.modulePtr);
};

ChiptuneJsPlayer.prototype.getNumChannels = function () {
  return libopenmpt._openmpt_module_get_num_channels(this.currentPlayingNode.modulePtr);
};

ChiptuneJsPlayer.prototype.getPatternNumRows = function (pattern) {
  return libopenmpt._openmpt_module_get_pattern_num_rows(this.currentPlayingNode.modulePtr, pattern);
};

ChiptuneJsPlayer.prototype.getPatternRowChannel = function (pattern, row, channel) {
  var modulePtr = this.currentPlayingNode.modulePtr;
  return {
    text: ChiptuneJsReadString(libopenmpt._openmpt_module_format_pattern_row_channel(modulePtr, pattern, row, channel, 0, 0)),
    highlight: ChiptuneJsReadString(libopenmpt._openmpt_module_highlight_pattern_row_channel(modulePtr, pattern, row, channel, 0, 0)),
  };
};

// returns the instrument or sample names
ChiptuneJsPlayer.prototype.getInstrumentNames = function () {
  var modulePtr = this.currentPlayingNode.modulePtr;
  var names = [];
  var numInstruments = libopenmpt._openmpt_module_get_num_instruments(modulePtr);
  if (numInstruments > 0) {
    for (var i = 0; i < numInstruments; i++) {
      names.push(ChiptuneJsReadString(libopenmpt._openmpt_module_get_instrument_name(modulePtr, i)));
    }
  } else {
    var numSamples = libopenmpt._openmpt_module_get_num_samples(modulePtr);
    for (var i = 0; i < numSamples; i++) {
      names.push(ChiptuneJsReadString(libopenmpt._openmpt_module_get_sample_name(modulePtr, i)));
    }
  }
  return names;
};

ChiptuneJsPlayer.prototype.getPlayingPosition = function () {
  var node = this.currentPlayingNode;
  if (node == null || node.modulePtr == 0) {
    return null;
  }
  node.updatePlayingPosition();
  return node.playingPosition;
};

ChiptuneJsPlayer.prototype.metadata = function() {
  var data = {};
  var keys = UTF8ToString(libopenmpt._openmpt_module_get_metadata_keys(this.currentPlayingNode.modulePtr)).split(';');
  var keyNameBuffer = 0;
  for (var i = 0; i < keys.length; i++) {
    keyNameBuffer = libopenmpt._malloc(keys[i].length + 1);
    //writeAsciiToMemory(keys[i], keyNameBuffer);
    stringToAscii(keys[i], keyNameBuffer);
    data[keys[i]] = UTF8ToString(libopenmpt._openmpt_module_get_metadata(this.currentPlayingNode.modulePtr, keyNameBuffer));
    libopenmpt._free(keyNameBuffer);
  }
  return data;
}

ChiptuneJsPlayer.prototype.module_ctl_set = function(ctl, value) {
  return libopenmpt.ccall('openmpt_module_ctl_set', 'number', ['number', 'string', 'string'], [this.currentPlayingNode.modulePtr, ctl, value]) === 1;
}

// playing, etc
ChiptuneJsPlayer.prototype.unlock = function() {

  var context = this.context;
  var buffer = context.createBuffer(1, 1, 22050);
  var unlockSource = context.createBufferSource();

  unlockSource.buffer = buffer;
  unlockSource.connect(context.destination);
  unlockSource.start(0);

  this.touchLocked = false;
}

ChiptuneJsPlayer.prototype.load = function(input, callback) {

  if (this.touchLocked) {
    this.unlock();
  }

  var player = this;

  if (input instanceof File) {
    var reader = new FileReader();
    reader.onload = function() {
      return callback(reader.result); // no error
    }.bind(this);
    reader.readAsArrayBuffer(input);
  } else {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', input, true);
    xhr.responseType = 'arraybuffer';
    xhr.onload = function(e) {
      if (xhr.status === 200) {
        return callback(xhr.response); // no error
      } else {
        player.fireEvent('onError', {type: 'onxhr'});
      }
    }.bind(this);
    xhr.onerror = function() {
      player.fireEvent('onError', {type: 'onxhr'});
    };
    xhr.onabort = function() {
      player.fireEvent('onError', {type: 'onxhr'});
    };
    xhr.send();
  }
}

ChiptuneJsPlayer.prototype.play = function(buffer) {
  this.stop();
  var processNode = this.createLibopenmptNode(buffer, this.config);
  if (processNode == null) {
    return;
  }

  // set config options on module
  libopenmpt._openmpt_module_set_repeat_count(processNode.modulePtr, this.config.repeatCount);
  libopenmpt._openmpt_module_set_render_param(processNode.modulePtr, OPENMPT_MODULE_RENDER_STEREOSEPARATION_PERCENT, this.config.stereoSeparation);
  libopenmpt._openmpt_module_set_render_param(processNode.modulePtr, OPENMPT_MODULE_RENDER_INTERPOLATIONFILTER_LENGTH, this.config.interpolationFilter);

  this.currentPlayingNode = processNode;
  processNode.connect(this.gainNode);
}

ChiptuneJsPlayer.prototype.stop = function() {
  if (this.currentPlayingNode != null) {
    this.currentPlayingNode.disconnect();
    this.currentPlayingNode.cleanup();
    this.currentPlayingNode = null;
  }
}

ChiptuneJsPlayer.prototype.togglePause = function() {
	if (this.currentPlayingNode != null) {
    this.currentPlayingNode.togglePause();
  }
}

ChiptuneJsPlayer.prototype.createLibopenmptNode = function(buffer, config) {
  // TODO error checking in this whole function

  var maxFramesPerChunk = 4096;
  var processNode = this.context.createScriptProcessor(2048, 0, 2);
  processNode.config = config;
  processNode.player = this;
  var byteArray = new Int8Array(buffer);
  var ptrToFile = libopenmpt._malloc(byteArray.byteLength);
  libopenmpt.HEAPU8.set(byteArray, ptrToFile);
  processNode.modulePtr = libopenmpt._openmpt_module_create_from_memory(ptrToFile, byteArray.byteLength, 0, 0, 0);
  processNode.paused = false;
  processNode.leftBufferPtr  = libopenmpt._malloc(4 * maxFramesPerChunk);
  processNode.rightBufferPtr = libopenmpt._malloc(4 * maxFramesPerChunk);
  processNode.positionQueue = [];
  processNode.readPosition = function(time) {
    return {
      time: time,
      order: libopenmpt._openmpt_module_get_current_order(this.modulePtr),
      pattern: libopenmpt._openmpt_module_get_current_pattern(this.modulePtr),
      row: libopenmpt._openmpt_module_get_current_row(this.modulePtr),
    };
  }
  processNode.playingPosition = processNode.modulePtr != 0 ? processNode.readPosition(0) : null;
  processNode.updatePlayingPosition = function() {
    var now = this.context.currentTime - (this.context.outputLatency || 0);
    var queue = this.positionQueue;
    while (queue.length > 0 && queue[0].time <= now) {
      this.playingPosition = queue.shift();
    }
  }
  processNode.cleanup = function() {
    if (this.modulePtr != 0) {
      libopenmpt._openmpt_module_destroy(this.modulePtr);
      this.modulePtr = 0;
    }
    if (this.leftBufferPtr != 0) {
      libopenmpt._free(this.leftBufferPtr);
      this.leftBufferPtr = 0;
    }
    if (this.rightBufferPtr != 0) {
      libopenmpt._free(this.rightBufferPtr);
      this.rightBufferPtr = 0;
    }
  }
  processNode.stop = function() {
    this.disconnect();
    this.cleanup();
  }
  processNode.pause = function() {
    this.paused = true;
  }
  processNode.unpause = function() {
    this.paused = false;
  }
  processNode.togglePause = function() {
    this.paused = !this.paused;
  }
  processNode.onaudioprocess = function(e) {
    var outputL = e.outputBuffer.getChannelData(0);
    var outputR = e.outputBuffer.getChannelData(1);
    var framesToRender = outputL.length;
    if (this.modulePtr == 0) {
      for (var i = 0; i < framesToRender; ++i) {
        outputL[i] = 0;
        outputR[i] = 0;
      }
      this.disconnect();
      this.cleanup();
      return;
    }
    if (this.paused) {
      for (var i = 0; i < framesToRender; ++i) {
        outputL[i] = 0;
        outputR[i] = 0;
      }
      return;
    }
    var framesRendered = 0;
    var ended = false;
    var error = false;
    // NOTE: playbackTime is when this buffer will be played, in context time
    var playbackTime = typeof e.playbackTime === 'number' ? e.playbackTime : this.context.currentTime;
    this.updatePlayingPosition();
    while (framesToRender > 0) {
      var framesPerChunk = Math.min(framesToRender, CHIPTUNE_POSITION_FRAMES);
      var queue = this.positionQueue;
      var last = queue.length > 0 ? queue[queue.length - 1] : this.playingPosition;
      var position = this.readPosition(playbackTime + framesRendered / this.context.sampleRate);
      if (last == null || position.order != last.order || position.pattern != last.pattern || position.row != last.row) {
        queue.push(position);
      }
      var actualFramesPerChunk = libopenmpt._openmpt_module_read_float_stereo(this.modulePtr, this.context.sampleRate, framesPerChunk, this.leftBufferPtr, this.rightBufferPtr);
      if (actualFramesPerChunk == 0) {
        ended = true;
        // modulePtr will be 0 on openmpt: error: openmpt_module_read_float_stereo: ERROR: module * not valid or other openmpt error
        error = !this.modulePtr;
      }
      var rawAudioLeft = libopenmpt.HEAPF32.subarray(this.leftBufferPtr / 4, this.leftBufferPtr / 4 + actualFramesPerChunk);
      var rawAudioRight = libopenmpt.HEAPF32.subarray(this.rightBufferPtr / 4, this.rightBufferPtr / 4 + actualFramesPerChunk);
      for (var i = 0; i < actualFramesPerChunk; ++i) {
        outputL[framesRendered + i] = rawAudioLeft[i];
        outputR[framesRendered + i] = rawAudioRight[i];
      }
      for (var i = actualFramesPerChunk; i < framesPerChunk; ++i) {
        outputL[framesRendered + i] = 0;
        outputR[framesRendered + i] = 0;
      }
      framesToRender -= framesPerChunk;
      framesRendered += framesPerChunk;
    }
    if (ended) {
      this.disconnect();
      this.cleanup();
      error ? processNode.player.fireEvent('onError', {type: 'openmpt'}) : processNode.player.fireEvent('onEnded');
    }
  }
  return processNode;
}
