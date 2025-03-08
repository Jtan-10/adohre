// faceValidation.js
// This module encapsulates the face recognition logic using face-api.js.
// It loads models, detects a face in an image (or canvas), and compares face descriptors.

(function(global) {
    /**
     * Loads the face-api.js models from the specified URL.
     * @param {string} modelUrl - The URL or path to the models folder.
     * @returns {Promise} Resolves when all models are loaded.
     */
    async function loadModels(modelUrl = 'backend/models/weights') {
      try {
        // Load required models. Ensure these files are in your model folder.
        await faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl);
        await faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl);
        await faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl);
        console.log("FaceValidation: Models loaded from", modelUrl);
      } catch (error) {
        console.error("FaceValidation: Error loading models", error);
      }
    }
  
    /**
     * Detects a single face in a given canvas element.
     * The canvas can be obtained from an image or video frame.
     * @param {HTMLCanvasElement} canvas - The canvas containing the face image.
     * @param {object} [options] - Optional detection options (default uses TinyFaceDetector).
     * @returns {Promise<object|null>} Returns detection result with landmarks and descriptor or null if no face is found.
     */
    async function detectFaceFromCanvas(canvas, options = new faceapi.TinyFaceDetectorOptions()) {
      try {
        const detection = await faceapi
          .detectSingleFace(canvas, options)
          .withFaceLandmarks()
          .withFaceDescriptor();
        if (detection) {
          console.log("FaceValidation: Face detected", detection);
        } else {
          console.warn("FaceValidation: No face detected");
        }
        return detection;
      } catch (error) {
        console.error("FaceValidation: Error detecting face", error);
        return null;
      }
    }
  
    /**
     * Compares two face descriptors using Euclidean distance.
     * A lower distance indicates a higher similarity.
     * @param {Float32Array} descriptor1 - The first face descriptor.
     * @param {Float32Array} descriptor2 - The second face descriptor.
     * @returns {number} The Euclidean distance between the descriptors.
     */
    function compareFaces(descriptor1, descriptor2) {
      const distance = faceapi.euclideanDistance(descriptor1, descriptor2);
      console.log("FaceValidation: Distance between faces:", distance);
      return distance;
    }
  
    // Expose the functions as a global object 'faceValidation'
    global.faceValidation = {
      loadModels: loadModels,
      detectFace: detectFaceFromCanvas,
      compareFaces: compareFaces
    };
  })(window);
  