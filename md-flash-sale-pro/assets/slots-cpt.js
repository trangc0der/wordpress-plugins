jQuery(document).ready(function ($) {
    function initSel($el) {
        if ($.fn.select2) {
            $el.select2({
                allowClear: true,
                placeholder: $el.data("placeholder") || "Gõ để tìm sản phẩm...",
                ajax: {
                    url: MDFS_ADMIN.ajax_url, // đã được wp_localize_script in ra
                    type: "GET",
                    dataType: "json",
                    delay: 250,
                    data: function (params) {
                        return {
                            action: "mdfs_search_products",
                            nonce: MDFS_ADMIN.nonce,
                            q: params.term || "",
                        };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                },
                width: "resolve",
            });
        }
    }

    // Khởi tạo select2 cho các dropdown sản phẩm đã có
    $(".mdfs-product-select").each(function () {
        initSel($(this));
    });

    // Nút thêm sản phẩm
    $("#mdfs-add-row").on("click", function (e) {
    e.preventDefault();
    var idx = $("#mdfs-products-table tbody tr").length;
    var tr = `
    <tr class="mdfs-row">
      <td data-label="Sản phẩm">
        <select class="mdfs-product-select mdfs-select" 
                name="mdfs_products[${idx}][product_id]" 
                data-placeholder="Gõ để tìm sản phẩm..."></select>
      </td>
      <td data-label="Biến thể (ID)"><input type="number" class="small-text" name="mdfs_products[${idx}][variation_id]" value="0" min="0"/></td>
      <td data-label="Giá KM"><input type="number" step="0.01" name="mdfs_products[${idx}][sale_price]" value=""/></td>
      <td data-label="Giá gốc"><input type="number" step="0.01" name="mdfs_products[${idx}][regular_price]" value=""/></td>
      <td data-label="Quota"><input type="number" name="mdfs_products[${idx}][quota]" value="0" min="0"/></td>
      <td data-label="Còn lại"><input type="number" name="mdfs_remain_new[]" value="0" min="0" disabled/></td>
      <td data-label="Badges"><input type="text" name="mdfs_products[${idx}][badges]" value="" placeholder="Mall|Yêu thích"/></td>
      <td data-label="Thumbnail">
        <div class="mdfs-thumb-wrap">
            <input type="hidden" name="mdfs_products[${idx}][thumb]" class="mdfs-thumb" />
            <img src="https://via.placeholder.com/60x60?text=+" class="mdfs-thumb-preview" style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;" />
            <br/>
            <button type="button" class="button mdfs-upload-thumb">Tải ảnh lên</button>
            <button type="button" class="button mdfs-remove-thumb">Xóa</button>
        </div>
      </td>
      <td class="mdfs-actions"><button type="button" class="button mdfs-remove">Xoá</button></td>
    </tr>`;

    $("#mdfs-products-table tbody").append(tr);

    // ngay khi thêm dòng -> biến select thành select2
    initSel($("#mdfs-products-table tbody tr:last .mdfs-product-select"));
});


    // Nút xoá sản phẩm
    $("#mdfs-products-table").on("click", ".mdfs-remove", function () {
        $(this).closest("tr").remove();
    });

    // Xử lý upload thumbnail
    var frame;
    $(document).on("click", ".mdfs-upload-thumb", function (e) {
        e.preventDefault();
        var button = $(this);
        frame = wp.media({
            title: "Chọn ảnh thumbnail",
            button: { text: "Dùng ảnh này" },
            multiple: false,
        });
        frame.on("select", function () {
            var attachment = frame.state().get("selection").first().toJSON();
            button.closest(".mdfs-thumb-wrap").find(".mdfs-thumb").val(attachment.url);
            button.closest(".mdfs-thumb-wrap").find(".mdfs-thumb-preview").attr("src", attachment.url);
        });
        frame.open();
    });

    // Xoá thumbnail
    $(document).on("click", ".mdfs-remove-thumb", function (e) {
        e.preventDefault();
        var wrap = $(this).closest(".mdfs-thumb-wrap");
        wrap.find(".mdfs-thumb").val("");
        wrap.find(".mdfs-thumb-preview").attr("src", "https://via.placeholder.com/60x60?text=+");
    });

    // Khi chọn sản phẩm -> tự lấy giá
    $(document).on("change", ".mdfs-product-select", function () {
        var $sel = $(this);
        var pid = $sel.val();
        if (!pid) return;

        $.ajax({
            url: MDFS_ADMIN.ajax_url,
            type: "GET",
            dataType: "json",
            data: {
                action: "mdfs_get_product_price",
                nonce: MDFS_ADMIN.nonce,
                id: pid,
            },
            success: function (res) {
                if (res.success && res.data) {
                    // tìm tới dòng hiện tại
                    var $row = $sel.closest("tr");
                    $row.find('input[name*="[regular_price]"]').val(res.data.regular_price || "");
                    if (res.data.sale_price) {
                        $row.find('input[name*="[sale_price]"]').val(res.data.sale_price);
                    }
                }
            },
        });
    });
    // Mở modal
    $("#mdfs-open-product-modal").on("click", function (e) {
        e.preventDefault();
        loadProductsModal(1, "", 10);
    });

    function loadProductsModal(page, search, perPage) {
        $("#mdfs-product-modal")
            .html(
                `
      <div class="mdfs-modal-overlay"></div>
      <div class="mdfs-modal-box">
        <div class="mdfs-modal-header">
          <h3>Chọn sản phẩm</h3>
          <button type="button" class="mdfs-close" id="mdfs-close-modal">×</button>
        </div>
        <div class="mdfs-modal-toolbar">
          <input type="text" id="mdfs-modal-search" placeholder="Tìm sản phẩm...">
          <select id="mdfs-modal-perpage">
            <option value="10">10 / trang</option>
            <option value="20">20 / trang</option>
            <option value="50">50 / trang</option>
          </select>
        </div>
        <div class="mdfs-modal-content"><p>Đang tải...</p></div>
        <div class="mdfs-modal-footer">
          <button type="button" class="button button-primary" id="mdfs-add-selected">Thêm vào</button>
          <button type="button" class="button" id="mdfs-close-modal">Đóng</button>
        </div>
      </div>
    `
            )
            .fadeIn();

        $.ajax({
            url: MDFS_ADMIN.ajax_url,
            type: "GET",
            data: {
                action: "mdfs_load_products_modal",
                nonce: MDFS_ADMIN.nonce,
                paged: page,
                s: search,
                per_page: perPage,
            },
            success: function (res) {
                if (res.success) {
                    $("#mdfs-product-modal .mdfs-modal-content").html(res.data);
                    $("#mdfs-modal-perpage").val(perPage);
                } else {
                    $("#mdfs-product-modal .mdfs-modal-content").html("<p>Lỗi tải sản phẩm</p>");
                }
            },
        });
    }

    // Tìm kiếm Enter
    $(document).on("keypress", "#mdfs-modal-search", function (e) {
        if (e.which == 13) {
            var perPage = $("#mdfs-modal-perpage").val();
            loadProductsModal(1, $(this).val(), perPage);
            return false;
        }
    });

    // Đổi số sản phẩm/trang
    $(document).on("change", "#mdfs-modal-perpage", function () {
        var perPage = $(this).val();
        var s = $("#mdfs-modal-search").val();
        loadProductsModal(1, s, perPage);
    });

    // Click trang / trước / sau
    $(document).on("click", ".mdfs-page, .mdfs-prev, .mdfs-next", function (e) {
        e.preventDefault();
        var page = $(this).data("page");
        var s = $("#mdfs-modal-search").val();
        var perPage = $("#mdfs-modal-perpage").val();
        loadProductsModal(page, s, perPage);
    });

    // Chọn tất cả
    $(document).on("change", "#mdfs-check-all", function () {
        $(".mdfs-modal-product").prop("checked", $(this).is(":checked"));
    });

    // Đóng modal
    $(document).on("click", "#mdfs-close-modal,.mdfs-modal-overlay", function () {
        $("#mdfs-product-modal").fadeOut();
    });

    // Thêm sản phẩm
    // Thêm sản phẩm từ modal xuống grid
$(document).on('click','#mdfs-add-selected', function(){
    $('#mdfs-product-modal .mdfs-modal-product:checked').each(function(){
        var id   = $(this).val();
        var name = $(this).data('name');
        var price= $(this).data('price');
        var idx  = $('#mdfs-products-table tbody tr').length;

        var tr = `
        <tr class="mdfs-row">
          <td data-label="Sản phẩm">
            <select class="mdfs-product-select mdfs-select" 
                    name="mdfs_products[${idx}][product_id]" 
                    data-placeholder="Gõ để tìm sản phẩm...">
              <option value="${id}" selected>${name}</option>
            </select>
          </td>
          <td data-label="Biến thể (ID)"><input type="number" class="small-text" name="mdfs_products[${idx}][variation_id]" value="0" min="0"/></td>
          <td data-label="Giá KM"><input type="number" step="0.01" name="mdfs_products[${idx}][sale_price]" value=""/></td>
          <td data-label="Giá gốc"><input type="number" step="0.01" name="mdfs_products[${idx}][regular_price]" value="${price}"/></td>
          <td data-label="Quota"><input type="number" name="mdfs_products[${idx}][quota]" value="0"/></td>
          <td data-label="Còn lại"><input type="number" name="mdfs_remain_new[]" value="0"/></td>
          <td data-label="Badges"><input type="text" name="mdfs_products[${idx}][badges]" value=""/></td>
          <td data-label="Thumbnail">
            <div class="mdfs-thumb-wrap">
              <input type="hidden" name="mdfs_products[${idx}][thumb]" class="mdfs-thumb" />
              <img src="https://via.placeholder.com/60x60?text=+" class="mdfs-thumb-preview" style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;" />
              <br/>
              <button type="button" class="button mdfs-upload-thumb">Tải ảnh lên</button>
              <button type="button" class="button mdfs-remove-thumb">Xóa</button>
            </div>
          </td>
          <td class="mdfs-actions"><button type="button" class="button mdfs-remove">Xoá</button></td>
        </tr>`;

        $('#mdfs-products-table tbody').append(tr);

        // gắn select2 cho select vừa thêm
        initSel($('#mdfs-products-table tbody tr:last .mdfs-product-select'));
    });

    $('#mdfs-product-modal').fadeOut();
});

});
