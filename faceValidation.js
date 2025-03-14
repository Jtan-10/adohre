// faceValidation.js
// This module encapsulates the face recognition logic using face-api.js.
// It loads models, detects a face in an image (or canvas), and compares face descriptors.

(function(global) {
    "use strict";
    /**
     * Loads the face-api.js models from the specified URL.
     * @param {string} modelUrl - The URL or path to the models folder.
     * @returns {Promise} Resolves when all models are loaded.
     */
    async function loadModels(modelUrl = 'backend/models/weights') {
      if (typeof modelUrl !== 'string') {
        throw new Error("Invalid modelUrl");
      }
      try {
        // Load required models.
        await faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl);
        await faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl);
        await faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl);
        // In production, limit success logging.
      } catch (error) {
        console.error("FaceValidation: Model load failed");
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
      if (!(canvas instanceof HTMLCanvasElement)) {
        throw new Error("Invalid canvas element");
      }
      try {
        const detection = await faceapi
          .detectSingleFace(canvas, options)
          .withFaceLandmarks()
          .withFaceDescriptor();
        // Avoid verbose logging in production.
        return detection;
      } catch (error) {
        console.error("FaceValidation: Face detection failed");
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
      if (!(descriptor1 instanceof Float32Array) || !(descriptor2 instanceof Float32Array)) {
        throw new Error("Invalid descriptor(s)");
      }
      const distance = faceapi.euclideanDistance(descriptor1, descriptor2);
      // Limit logging in production.
      return distance;
    }
  
    // Freeze the API object to prevent external modifications.
    const api = Object.freeze({
      loadModels: loadModels,
      detectFace: detectFaceFromCanvas,
      compareFaces: compareFaces
    });
    global.faceValidation = api;
})(window);
