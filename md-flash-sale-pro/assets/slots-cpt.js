jQuery(document).ready(function ($) {
    /** ===========================
     *  Hàm khởi tạo Select2
     *  =========================== */
    function initSel($el) {
        if ($.fn.select2) {
            $el.select2({
                allowClear: true,
                placeholder: $el.data("placeholder") || "Gõ để tìm sản phẩm...",
                ajax: {
                    url: MDFS_ADMIN.ajax_url,
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

    /** ===========================
     *  Hàm gọi AJAX lấy giá sản phẩm
     *  =========================== */
    function fetchProductPrice($row, pid, vid) {
        if (!pid) return;
        $.ajax({
            url: MDFS_ADMIN.ajax_url,
            type: "GET",
            dataType: "json",
            data: {
                action: "mdfs_get_product_price",
                nonce: MDFS_ADMIN.nonce,
                product_id: pid,
                variation_id: vid || 0,
            },
            success: function (res) {
                if (res.success && res.data) {
                    var d = res.data;

                    // Biến thể ID
                    $row.find('input[name*="[variation_id]"]').val(d.variation_id || 0);

                    // Giá gốc
                    var reg = parseFloat(d.regular_price) || 0;
                    $row.find('input[name*="[regular_price]"]').val(reg);

                    // Giá KM → chỉ fill nếu có
                    if (d.sale_price && d.sale_price !== "") {
                        $row.find('input[name*="[sale_price]"]').val(d.sale_price);
                    }
                }
            },
        });
    }

    /** ===========================
     *  Khởi tạo Select2 cho rows đã có
     *  =========================== */
    $(".mdfs-product-select").each(function () {
        initSel($(this));
    });

    /** ===========================
     *  Thêm sản phẩm mới (Add Row)
     *  =========================== */
    $("#mdfs-add-row").on("click", function (e) {
        e.preventDefault();
        var idx = $("#mdfs-products-table tbody tr").length;
        var tr = `
        <tr class="mdfs-row">
          <td>
            <select class="mdfs-product-select mdfs-select"
                    name="mdfs_products[${idx}][product_id]"
                    data-placeholder="Gõ để tìm sản phẩm..."></select>
          </td>
          <td>
            <input type="text" class="small-text"
                   name="mdfs_products[${idx}][variation_id]"
                   value="0" readonly/>
          </td>
          <td><input type="number" step="0.01" name="mdfs_products[${idx}][sale_price]" value=""/></td>
          <td><input type="number" step="0.01" name="mdfs_products[${idx}][regular_price]" value="" readonly/></td>
          <td><input type="number" name="mdfs_products[${idx}][quota]" value="0" min="0"/></td>
          <td><input type="number" value="0" readonly class="mdfs-sold"/></td>
          <td><input type="number" value="0" readonly class="mdfs-remain"/></td>
          <td><input type="text" name="mdfs_products[${idx}][badges]" value="" placeholder="Mall|Yêu thích"/></td>
          <td>
            <div class="mdfs-thumb-wrap">
                <input type="hidden" name="mdfs_products[${idx}][thumb]" class="mdfs-thumb" />
                <img src="/wp-content/uploads/2025/09/image-not-found.png" class="mdfs-thumb-preview"
                     style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;" />
                <br/>
                <button type="button" class="button mdfs-upload-thumb">Tải ảnh lên</button>
                <button type="button" class="button mdfs-remove-thumb">Xóa</button>
            </div>
          </td>
          <td class="mdfs-actions"><button type="button" class="button mdfs-remove">Xoá</button></td>
        </tr>`;

        $("#mdfs-products-table tbody").append(tr);
        var $newSel = $("#mdfs-products-table tbody tr:last .mdfs-product-select");
        initSel($newSel);
    });

    /** ===========================
     *  Xoá sản phẩm
     *  =========================== */
    $("#mdfs-products-table").on("click", ".mdfs-remove", function () {
        $(this).closest("tr").remove();
    });

    /** ===========================
     *  Upload & Xoá thumbnail
     *  =========================== */
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
            var att = frame.state().get("selection").first().toJSON();
            button.closest(".mdfs-thumb-wrap").find(".mdfs-thumb").val(att.url);
            button.closest(".mdfs-thumb-wrap").find(".mdfs-thumb-preview").attr("src", att.url);
        });
        frame.open();
    });

    $(document).on("click", ".mdfs-remove-thumb", function (e) {
        e.preventDefault();
        var wrap = $(this).closest(".mdfs-thumb-wrap");
        wrap.find(".mdfs-thumb").val("");
        wrap.find(".mdfs-thumb-preview").attr("src", "/wp-content/uploads/2025/09/image-not-found.png");
    });

    /** ===========================
     *  Khi chọn sản phẩm (Select2)
     *  =========================== */
    $(document).on("change", ".mdfs-product-select", function () {
        var $sel = $(this);
        var pid = $sel.val();
        if (!pid) return;
        var $row = $sel.closest("tr");
        var vid = $row.find('input[name*="[variation_id]"]').val() || 0;
        fetchProductPrice($row, pid, vid);
    });

    /** ===========================
     *  Khi thay đổi quota → cập nhật "Còn lại"
     *  =========================== */
    $(document).on("input", 'input[name*="[quota]"]', function () {
        var $row = $(this).closest("tr");
        var quota = parseInt($(this).val()) || 0;
        var sold = parseInt($row.find(".mdfs-sold").val()) || 0;
        var remain = Math.max(0, quota - sold);
        $row.find(".mdfs-remain").val(remain);
    });

    /** ===========================
     *  Modal chọn nhiều sản phẩm
     *  =========================== */
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
          </div>`
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
            loadProductsModal(1, $(this).val(), $("#mdfs-modal-perpage").val());
            return false;
        }
    });

    // Đổi số /trang
    $(document).on("change", "#mdfs-modal-perpage", function () {
        loadProductsModal(1, $("#mdfs-modal-search").val(), $(this).val());
    });

    // Chuyển trang
    $(document).on("click", ".mdfs-page, .mdfs-prev, .mdfs-next", function (e) {
        e.preventDefault();
        loadProductsModal($(this).data("page"), $("#mdfs-modal-search").val(), $("#mdfs-modal-perpage").val());
    });

    // Check all
    $(document).on("change", "#mdfs-check-all", function () {
        $(".mdfs-modal-product").prop("checked", $(this).is(":checked"));
    });

    // Đóng modal
    $(document).on("click", "#mdfs-close-modal,.mdfs-modal-overlay", function () {
        $("#mdfs-product-modal").fadeOut();
    });

    // Thêm sản phẩm từ modal
    $(document).on("click", "#mdfs-add-selected", function () {
        $("#mdfs-product-modal .mdfs-modal-product:checked").each(function () {
            var id = $(this).val();
            var name = $(this).data("name");
            var price = $(this).data("price") || "";
            var sale = $(this).data("sale") || "";
            var vid = $(this).data("variation_id") || 0;
            var idx = $("#mdfs-products-table tbody tr").length;

            var tr = `
            <tr class="mdfs-row">
              <td>
                <select class="mdfs-product-select mdfs-select"
                        name="mdfs_products[${idx}][product_id]">
                  <option value="${id}" selected>${name}</option>
                </select>
              </td>
              <td><input type="text" class="small-text" name="mdfs_products[${idx}][variation_id]" value="${vid}" readonly/></td>
              <td><input type="number" step="0.01" name="mdfs_products[${idx}][sale_price]" value="${sale}"/></td>
              <td><input type="number" step="0.01" name="mdfs_products[${idx}][regular_price]" value="${price}" readonly/></td>
              <td><input type="number" name="mdfs_products[${idx}][quota]" value="0" min="0"/></td>
              <td><input type="number" value="0" readonly class="mdfs-sold"/></td>
              <td><input type="number" value="0" readonly class="mdfs-remain"/></td>
              <td><input type="text" name="mdfs_products[${idx}][badges]" value="" placeholder="Mall|Yêu thích"/></td>
              <td>
                <div class="mdfs-thumb-wrap">
                  <input type="hidden" name="mdfs_products[${idx}][thumb]" class="mdfs-thumb"/>
                  <img src="/wp-content/uploads/2025/09/image-not-found.png" class="mdfs-thumb-preview" style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;"/>
                  <br/>
                  <button type="button" class="button mdfs-upload-thumb">Tải ảnh lên</button>
                  <button type="button" class="button mdfs-remove-thumb">Xóa</button>
                </div>
              </td>
              <td class="mdfs-actions"><button type="button" class="button mdfs-remove">Xoá</button></td>
            </tr>`;

            $("#mdfs-products-table tbody").append(tr);
            initSel($("#mdfs-products-table tbody tr:last .mdfs-product-select"));
        });
        $("#mdfs-product-modal").fadeOut();
    });
});
