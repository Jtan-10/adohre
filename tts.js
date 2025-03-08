// tts.js
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
      // Debug log
      console.log("TTS.speakMessage called with:", message);
  
      const utterance = new SpeechSynthesisUtterance(message);
      utterance.lang = 'en-GB';
  
      // onend fires when the speech finishes normally
      utterance.onend = function() {
        console.log("TTS.speakMessage ended");
        if (callback) callback();
      };
  
      // onerror fires if there's a speech synthesis error
      utterance.onerror = function(e) {
        console.error("TTS.speakMessage error:", e);
        // Optionally call the callback so the app can continue
        if (callback) callback();
      };
  
      // Function to select a voice
      function setVoice() {
        let voices = window.speechSynthesis.getVoices();
        console.log("TTS voices available:", voices);
        let selectedVoice = voices.find(voice =>
          voice.lang === 'en-GB' && voice.name.toLowerCase().includes('female')
        ) || voices.find(voice => voice.lang === 'en-GB') || voices[0];
        console.log("TTS using voice:", selectedVoice);
  
        utterance.voice = selectedVoice;
        window.speechSynthesis.speak(utterance);
      }
  
      // If voices aren't loaded yet, wait for onvoiceschanged
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
      console.log("TTS.speakTextInChunks called. Raw text length:", text.length);
  
      // Sanitize the text to remove hidden chars or HTML that might break TTS
      text = sanitizeText(text);
      console.log("TTS sanitized text length:", text.length);
  
      // Split text into chunks
      let chunks = [];
      while (text.length > 0) {
        let chunk = text.substring(0, maxChunkLength);
        let lastSpace = chunk.lastIndexOf(" ");
        // If we haven't reached the end, try not to cut off a word
        if (lastSpace > 0 && text.length > maxChunkLength) {
          chunk = text.substring(0, lastSpace);
        }
        chunks.push(chunk.trim());
        text = text.substring(chunk.length).trim();
      }
  
      console.log("TTS chunks:", chunks);
  
      function speakChunk(index) {
        if (index >= chunks.length) {
          console.log("TTS finished all chunks");
          return;
        }
        console.log("TTS speaking chunk", index, ":", chunks[index]);
  
        const utterance = new SpeechSynthesisUtterance(chunks[index]);
        utterance.lang = 'en-GB';
  
        // onend fires when the chunk finishes normally
        utterance.onend = function() {
          console.log("Chunk", index, "ended");
          speakChunk(index + 1);
        };
  
        // onerror fires if there's a speech synthesis error
        utterance.onerror = function(e) {
          console.error("Speech error for chunk", index, e);
          // Move on to the next chunk or stop
          speakChunk(index + 1);
        };
  
        // Choose a voice
        let voices = window.speechSynthesis.getVoices();
        let selectedVoice = voices.find(voice =>
          voice.lang === 'en-GB' && voice.name.toLowerCase().includes('female')
        ) || voices.find(voice => voice.lang === 'en-GB') || voices[0];
        utterance.voice = selectedVoice;
  
        // Actually speak
        window.speechSynthesis.speak(utterance);
      }
  
      // Start with the first chunk
      speakChunk(0);
    }
  
    // Expose these functions globally
    global.TTS = {
      speakMessage: speakMessage,
      speakTextInChunks: speakTextInChunks
    };
  })(window);
  