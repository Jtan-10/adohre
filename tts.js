"use strict";

(function(global) {
    /**
     * Utility function to sanitize text before chunking:
     * - Removes non-breaking spaces
     * - Strips HTML tags
     * - Converts smart quotes to normal quotes
     * - (You can add more rules as needed)
     */
    function sanitizeText(text) {
      return text
        .replace(/\u00a0/g, ' ')     // replace non-breaking spaces
        .replace(/<[^>]+>/g, '')     // strip HTML tags
        .replace(/[“”]/g, '"')       // replace smart quotes
        .replace(/[‘’]/g, "'");      // replace smart apostrophes
    }
  
    /**
     * Speak a short message (non-chunked) with an optional callback when done.
     */
    function speakMessage(message, callback) {
      const utterance = new SpeechSynthesisUtterance(message);
      utterance.lang = 'en-GB';
  
      utterance.onend = function() {
        if (callback) callback();
      };
  
      utterance.onerror = function(e) {
        if (callback) callback();
      };
  
      function setVoice() {
        let voices = window.speechSynthesis.getVoices();
        let selectedVoice = voices.find(voice =>
          voice.lang === 'en-GB' && voice.name.toLowerCase().includes('female')
        ) || voices.find(voice => voice.lang === 'en-GB') || voices[0];
  
        utterance.voice = selectedVoice;
        window.speechSynthesis.speak(utterance);
      }
  
      if (window.speechSynthesis.getVoices().length === 0) {
        window.speechSynthesis.onvoiceschanged = setVoice;
      } else {
        setVoice();
      }
    }
  
    /**
     * Speak long text by splitting it into chunks.
     * Default chunk size is set to 100 for testing smaller segments.
     */
    function speakTextInChunks(text, maxChunkLength = 100) {
      text = sanitizeText(text);
  
      let chunks = [];
      while (text.length > 0) {
        let chunk = text.substring(0, maxChunkLength);
        let lastSpace = chunk.lastIndexOf(" ");
        if (lastSpace > 0 && text.length > maxChunkLength) {
          chunk = text.substring(0, lastSpace);
        }
        chunks.push(chunk.trim());
        text = text.substring(chunk.length).trim();
      }
  
      function speakChunk(index) {
        if (index >= chunks.length) {
          return;
        }
  
        const utterance = new SpeechSynthesisUtterance(chunks[index]);
        utterance.lang = 'en-GB';
  
        utterance.onend = function() {
          speakChunk(index + 1);
        };
  
        utterance.onerror = function(e) {
          speakChunk(index + 1);
        };
  
        let voices = window.speechSynthesis.getVoices();
        let selectedVoice = voices.find(voice =>
          voice.lang === 'en-GB' && voice.name.toLowerCase().includes('female')
        ) || voices.find(voice => voice.lang === 'en-GB') || voices[0];
        utterance.voice = selectedVoice;
  
        window.speechSynthesis.speak(utterance);
      }
  
      speakChunk(0);
    }
  
    // Expose these functions globally
    global.TTS = {
      speakMessage: speakMessage,
      speakTextInChunks: speakTextInChunks
    };
})(window);
