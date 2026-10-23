{{-- Hộp thoại xác nhận dùng chung cho các form có thuộc tính data-confirm --}}
<dialog class="accessible-dialog confirm-modal" id="confirm-dialog" aria-label="Xác nhận thao tác">
    <button class="icon-button modal-close" type="button" data-close-dialog aria-label="Đóng"><x-icon name="x" /></button>
    <div class="confirm-icon danger"><x-icon name="alert-triangle" /></div>
    <h3 data-confirm-title>Xác nhận thao tác</h3>
    <p data-confirm-message></p>
    <div class="modal-actions">
        <button class="btn btn-light" type="button" data-close-dialog>Hủy</button>
        <button class="btn btn-danger" type="button" data-confirm-accept>Xác nhận</button>
    </div>
</dialog>
