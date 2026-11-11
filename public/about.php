<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

render_header('About DriveEase', 'about');
?>
<section class="bg-brand text-white py-5">
  <div class="container py-lg-3">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <span class="badge text-bg-warning mb-3">ABOUT DRIVE EASE</span>
        <h1 class="display-5 fw-bold mb-3">Every journey starts with a better booking experience.</h1>
        <p class="lead text-white-50 mb-0">DriveEase brings vehicle discovery, rental bookings and fleet operations together in one straightforward car-rental management platform.</p>
      </div>
      <div class="col-lg-4 text-lg-end"><i class="fa-solid fa-car-side" style="font-size:clamp(5rem,14vw,9rem);opacity:.8" aria-hidden="true"></i></div>
    </div>
  </div>
</section>
<section class="container py-5">
  <div class="row g-4 align-items-stretch">
    <div class="col-lg-7">
      <h2 class="h3 fw-bold mb-3">What is DriveEase?</h2>
      <p class="text-muted">DriveEase is a car-rental management application designed to make the main rental journey easier to follow: explore cars, review vehicle details, create bookings, track active rentals and manage returns and invoices.</p>
      <p class="text-muted">Customers have a dedicated booking experience, while fleet owners can manage vehicles, drivers, rentals and payment records from their workspace.</p>
      <div class="row g-3 mt-2">
        <div class="col-sm-6"><div class="card h-100 border-0 bg-light"><div class="card-body"><i class="fa-solid fa-magnifying-glass text-brand fs-4 mb-3" aria-hidden="true"></i><h3 class="h6 fw-bold">Discover</h3><p class="small text-muted mb-0">Browse the available fleet and filter cars to suit your trip.</p></div></div></div>
        <div class="col-sm-6"><div class="card h-100 border-0 bg-light"><div class="card-body"><i class="fa-solid fa-calendar-check text-brand fs-4 mb-3" aria-hidden="true"></i><h3 class="h6 fw-bold">Manage</h3><p class="small text-muted mb-0">Keep booking, rental, return and invoice information organised.</p></div></div></div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card h-100 border-0 shadow-sm"><div class="card-body p-4">
        <span class="badge text-bg-light mb-3">PROJECT CREDITS</span>
        <h2 class="h4 fw-bold">Built on open-source work</h2>
        <p class="text-muted small">This project recognises both its original creator and the developer continuing its improvements.</p>
        <hr>
        <div class="d-flex gap-3 align-items-start mb-4">
          <div class="rounded-circle bg-light p-3"><i class="fa-solid fa-code-branch text-brand fs-4" aria-hidden="true"></i></div>
          <div><h3 class="h6 fw-bold mb-1">Original developer</h3><p class="small text-muted mb-2">Nikhil Amin — original Patna Car Rental project.</p><a href="https://github.com/nikhil-amin/Patna-Car-Rental" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm"><i class="fa-brands fa-github me-1" aria-hidden="true"></i>Original repository</a></div>
        </div>
        <div class="d-flex gap-3 align-items-start">
          <div class="rounded-circle bg-light p-3"><i class="fa-solid fa-laptop-code text-brand fs-4" aria-hidden="true"></i></div>
          <div><h3 class="h6 fw-bold mb-1">Current enhancements</h3><p class="small text-muted mb-2">Ashutosh Palhare — ongoing development, maintenance and product improvements.</p><div class="d-flex flex-wrap gap-2"><a href="https://github.com/ashutoshpalhare" target="_blank" rel="noopener noreferrer" class="btn btn-brand btn-sm"><i class="fa-brands fa-github me-1" aria-hidden="true"></i>GitHub</a><a href="https://ashutoshpalhare.github.io/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm">Portfolio</a></div></div>
        </div>
      </div></div>
    </div>
  </div>
</section>
<section class="container pb-5">
  <div class="rounded-3 bg-light p-4 p-md-5 text-center">
    <h2 class="h4 fw-bold mb-2">Connect with the developer</h2>
    <p class="text-muted mb-4">Follow along for projects, development updates and more.</p>
    <div class="d-flex justify-content-center flex-wrap gap-2">
      <a class="btn btn-dark" href="https://github.com/ashutoshpalhare" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-github me-2" aria-hidden="true"></i>GitHub</a>
      <a class="btn btn-primary" href="https://www.linkedin.com/in/ashutoshpalhare" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-linkedin-in me-2" aria-hidden="true"></i>LinkedIn</a>
      <a class="btn btn-outline-dark" href="https://twitter.com/ashutoshpalhare" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-x-twitter me-2" aria-hidden="true"></i>X / Twitter</a>
      <a class="btn btn-danger" href="https://www.instagram.com/ashutoshpalhare/" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-instagram me-2" aria-hidden="true"></i>Instagram</a>
      <a class="btn btn-primary" href="https://www.facebook.com/ashutoshpalhare/" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-facebook me-2" aria-hidden="true"></i>Facebook</a>
      <a class="btn btn-outline-secondary" href="https://ashutoshpalhare.github.io/" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-globe me-2" aria-hidden="true"></i>Website</a>
    </div>
  </div>
</section>
<?php render_footer();
