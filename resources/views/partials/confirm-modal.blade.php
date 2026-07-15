{{-- Shared confirm-delete modal. Any <form> can opt in with
     data-sas-confirm="Are you sure?" (see layouts/app.blade.php for the JS
     that intercepts the submit and shows this). --}}
<div class="modal fade" id="sasConfirmModal" tabindex="-1" aria-labelledby="sasConfirmModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2" id="sasConfirmModalTitle">
          <span class="sas-confirm-icon"><i class="fi fi-rr-trash"></i></span>
          Please confirm
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="sasConfirmModalBody">Are you sure?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="sasConfirmModalBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>
