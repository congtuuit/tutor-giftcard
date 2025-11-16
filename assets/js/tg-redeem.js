(function ($) {
  $(document).ready(function () {
    // Redeem page app
    var $app = $("#tg-redeem-app");
    if ($app.length) {
      var status = $app.data("status") || "0";
      if (status == "1") {
        $("#tg-redeem-result").text("Thẻ đã được sử dụng.");
        $("#tg-redeem-result").css("color", "red");
        Swal.fire({
          icon: "info",
          title: "Thẻ đã được sử dụng",
        });
      }

      var max = parseInt($app.data("max") || 1);
      var selected = [];

      function refreshSelected() {
        var $list = $("#tg-selected-list").empty();
        selected.forEach(function (item) {
          // Tạo thẻ div cho mỗi item
          var $el = $("<div>").addClass("selected-item").css({
            display: "flex",
            alignItems: "center",
            gap: "8px",
            marginBottom: "6px",
          });
          // Thumbnail
          if (item.thumb) {
            var $thumb = $("<img>").attr("src", item.thumb).css({
              width: "50px",
              height: "50px",
              objectFit: "cover",
              borderRadius: "4px",
            });
            $el.append($thumb);
          }
          // Title + link
          var $link = $("<a>").attr("href", item.link).text(item.title).css({
            fontSize: "14px",
            textDecoration: "none",
            color: "#333",
          });
          $el.append($link);

          // Optional: ID nhỏ bên cạnh
          var $id = $("<span style='display:none;'>")
            .text(" (ID: " + item.id + ")")
            .css({
              fontSize: "12px",
              color: "#666",
            });
          $el.append($id);

          // Thêm vào container
          $list.append($el);
        });

        $("#tg-selected-count").text(selected.length);
        $("#tg-confirm-redeem").prop("disabled", selected.length === 0);
      }

      $app.on("change", ".tg-course-checkbox", function () {
        var id = $(this).val();
        var title = $(this).data("title") || "";
        var link = $(this).data("link") || "#";
        var thumb = $(this).data("thumb") || "";

        if ($(this).is(":checked")) {
          if (selected.length >= max) {
            alert("Bạn chỉ được chọn tối đa " + max + " khóa học.");
            $(this).prop("checked", false);
            return;
          }
          selected.push({
            id: id,
            title: title,
            link: link,
            thumb: thumb,
          });
        } else {
          selected = selected.filter(function (x) {
            return x.id != id;
          });
        }
        refreshSelected();
      });

      // Click claim gifts
      $("#tg-confirm-redeem").on("click", function () {
        var code = $app.data("code");
        var giftcard = $app.data("giftcard");
        var record_id = $app.data("id");
        if (!code) return alert("Missing code.");
        $(this).prop("disabled", true).text("Đang gửi...");
        // Call REST endpoint /tutor-giftcard/v1/redeem
        $.ajax({
          url: TG_REDEEM.ajax_url,
          method: "POST",
          beforeSend: function (xhr) {
            xhr.setRequestHeader("X-WP-Nonce", TG_REDEEM.nonce);
          },
          data: {
            action: "redeem_course",
            record_id: record_id,
            id: giftcard,
            giftcode: code,
            selected_ids: selected.map(function (x) {
              return x.id;
            }),
          },
          success: function (resp) {
            if (resp.success) {
              $("#tg-redeem-result").text("Đổi thành công.");
              $("#tg-redeem-result").css("color", "green");
            } else {
              $("#tg-redeem-result").text(resp.data || "Lỗi khi đổi.");
              $("#tg-redeem-result").css("color", "red");
              $("#tg-confirm-redeem")
                .prop("disabled", false)
                .text("Xác nhận đổi");
            }
          },
          error: function () {
            $("#tg-redeem-result").text(
              "Thẻ đã được sử dụng vui lòng kiếm tra lại."
            );
            $("#tg-redeem-result").css("color", "red");
            $("#tg-confirm-redeem")
              .prop("disabled", false)
              .text("Xác nhận đổi");
          },
        });
      });
    }
  });
})(jQuery);

document.addEventListener("DOMContentLoaded", () => {
  console.log("TG Redeem JS loaded");

  let currentPage = 1;

  function loadCourses(page = 1) {
    var status = $("#tg-redeem-app").data("status") || "0";
    if (status == "1") {
      return;
    }
    showLoading(true);
    console.log("Loading courses for page:", page);
    const search = document.getElementById("tg-search-input").value.trim();

    const form = new FormData();
    form.append("action", "tg_filter_courses");
    form.append("page", page);
    form.append("search", search);
    form.append("tg_giftcard", JSON.stringify(window.TG_GIFTCARD));

    fetch(window.TG_REDEEM.ajax_url, {
      method: "POST",
      body: form,
    })
      .then((res) => res.json())
      .then((data) => {
        document.getElementById("tg-course-grid").innerHTML = data.html;
        renderPagination(data.current, data.max_page);
        showLoading(false);
      });
  }

  function showLoading(show) {
    const spinner = document.getElementById("tg-loading-spinner");
    spinner.style.display = show ? "flex" : "none";
  }

  /**
   * Render pagination với UX/UI tốt hơn.
   * @param {number} current - Trang hiện tại (ví dụ: 5)
   * @param {number} maxPage - Tổng số trang (ví dụ: 20)
   */
  function renderPagination(current, maxPage) {
    const wrapId = "tg-pagination";
    let wrap = document.getElementById(wrapId);

    // Tạo container nếu chưa tồn tại
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.id = wrapId;
      // Sử dụng class thay vì style nội tuyến
      wrap.className = "tg-pagination";
      // Đảm bảo bạn có một element với ID 'tgPaginationPlaceholder' trong HTML của mình
      document
        .querySelector("#tgPaginationPlaceholder")
        .insertAdjacentElement("afterend", wrap);
    }

    let html = "";
    // Số trang hiển thị ở 2 bên của trang hiện tại
    const pagesToShow = 2;

    // --- 1. Nút "Trước" (Previous) ---
    if (current > 1) {
      html += `<button data-page="${
        current - 1
      }" class="tg-page-btn prev">&laquo; Trước</button>`;
    } else {
      html += `<button class="tg-page-btn prev disabled" disabled>&laquo; Trước</button>`;
    }

    // --- 2. Logic hiển thị các số trang ---
    const pageNumbers = new Set();
    pageNumbers.add(1); // Luôn hiển thị trang 1
    pageNumbers.add(maxPage); // Luôn hiển thị trang cuối

    // Thêm các trang xung quanh trang hiện tại
    for (let i = -pagesToShow; i <= pagesToShow; i++) {
      const page = current + i;
      if (page > 0 && page <= maxPage) {
        pageNumbers.add(page);
      }
    }

    // Sắp xếp các trang và thêm dấu "..."
    const sortedPages = Array.from(pageNumbers).sort((a, b) => a - b);
    let lastPage = 0;

    for (const page of sortedPages) {
      // Nếu có khoảng cách lớn giữa các số trang, thêm "..."
      if (lastPage !== 0 && page - lastPage > 1) {
        html += `<span class="tg-page-ellipsis">...</span>`;
      }

      const activeClass = page === current ? "active" : "";
      html += `<button data-page="${page}" class="tg-page-btn ${activeClass}">${page}</button>`;
      lastPage = page;
    }

    // --- 3. Nút "Sau" (Next) ---
    if (current < maxPage) {
      html += `<button data-page="${
        current + 1
      }" class="tg-page-btn next">Tiếp &raquo;</button>`;
    } else {
      html += `<button class="tg-page-btn next disabled" disabled>Tiếp &raquo;</button>`;
    }

    // --- 4. Render và gắn sự kiện ---
    wrap.innerHTML = html;

    // Gắn sự kiện click cho các nút có thể bấm (không bị disabled)
    wrap.querySelectorAll(".tg-page-btn:not(.disabled)").forEach((btn) => {
      btn.addEventListener("click", () => {
        // Giả sử 'currentPage' và 'loadCourses' là biến/hàm toàn cục
        // mà bạn có thể truy cập
        currentPage = parseInt(btn.dataset.page);
        loadCourses(currentPage);
      });
    });
  }

  // Search button
  document
    .getElementById("tg-search-btn")
    .addEventListener("click", function () {
      loadCourses(1);
    });

  // Search on typing (500ms debounce)
  let debounce;
  document
    .getElementById("tg-search-input")
    .addEventListener("keyup", function () {
      clearTimeout(debounce);
      debounce = setTimeout(() => loadCourses(1), 500);
    });

  // Initial load
  loadCourses(1);
});
