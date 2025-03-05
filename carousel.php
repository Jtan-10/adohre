<style>
  /* Set the carousel to fill the full viewport height */
  #carouselExampleIndicators .carousel-inner {
    height: 100vh;
  }

  /* Force images to fill the entire carousel area while ensuring the bottom is visible */
  #carouselExampleIndicators .carousel-item img {
    width: 100%;
    height: 100vh;            /* Force image height to be full viewport height */
    object-fit: cover;        /* Cover the container uniformly */
    object-position: bottom;  /* Always show the bottom of the image */
  }
  
  /* Ensure each carousel item is positioned relatively */
  #carouselExampleIndicators .carousel-item {
    position: relative;
  }

  /* Always display captions with consistent styling */
  #carouselExampleIndicators .carousel-caption {
    z-index: 10;
    display: block !important;
    position: absolute;
    bottom: 10%;   /* Adjust if needed */
    left: 15%;  
    right: 15%;
    text-align: center;
    background: rgba(0, 0, 0, 0.5);
    padding: 10px 20px;
    border-radius: 5px;
  }

  /* Explicit caption text styling */
  #carouselExampleIndicators .carousel-caption h5,
  #carouselExampleIndicators .carousel-caption p {
    color: #fff;
    margin: 0;
  }
  
  /* Ensure carousel arrows are above captions */
  #carouselExampleIndicators .carousel-control-prev,
  #carouselExampleIndicators .carousel-control-next {
    z-index: 20;
  }
</style>

<div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-indicators">
    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active"
      aria-current="true" aria-label="Slide 1"></button>
    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1"
      aria-label="Slide 2"></button>
    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2"
      aria-label="Slide 3"></button>
  </div>
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img src="assets/pexels-pixabay-164572.jpg" class="d-block w-100" alt="Slide 1">
      <div class="carousel-caption">
        <h5>Welcome to ADOHRE</h5>
        <p>Discover ADOHRE: Your Best Chapter is Here!</p>
      </div>
    </div>
    <div class="carousel-item">
      <img src="assets/pexels-pixabay-269077.jpg" class="d-block w-100" alt="Slide 2">
      <div class="carousel-caption">
        <h5>Empowering Retired Employees</h5>
        <p>Join our community to grow together.</p>
      </div>
    </div>
    <div class="carousel-item">
      <img src="assets/pexels-sevenstormphotography-443383.jpg" class="d-block w-100" alt="Slide 3">
      <div class="carousel-caption">
        <h5>Make a Difference</h5>
        <p>Participate in our events and trainings.</p>
      </div>
    </div>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>
