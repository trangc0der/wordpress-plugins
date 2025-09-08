<?php
if ( ! defined('ABSPATH') ) exit;

add_action('admin_menu', function(){
  add_submenu_page(
    'edit.php?post_type=mdfs_slot',
    __('Flash Sale Reports','mdfs-rt'),
    __('Reports','mdfs-rt'),
    'mdfs_view_analytics',
    'mdfs_reports',
    'mdfs_rt_reports_page'
  );
});

function mdfs_rt_reports_page(){
  $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d', strtotime('-14 days'));
  $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : date('Y-m-d');
  $nonce = wp_create_nonce('wp_rest');
  ?>
  <div class="wrap">
    <h1>Flash Sale Reports</h1>
    <form method="get" style="margin:12px 0">
      <input type="hidden" name="post_type" value="mdfs_slot"/>
      <input type="hidden" name="page" value="mdfs_reports"/>
      <label>Từ ngày: <input type="date" name="from" value="<?php echo esc_attr($from); ?>"/></label>
      <label>Đến ngày: <input type="date" name="to" value="<?php echo esc_attr($to); ?>"/></label>
      <button class="button button-primary">Xem báo cáo</button>
      <a class="button" target="_blank" href="<?php echo esc_url( rest_url('mdfs/v1/reports/export?type=slots&from='.$from.'&to='.$to) ); ?>">Export CSV (Slots)</a>
      <a class="button" target="_blank" href="<?php echo esc_url( rest_url('mdfs/v1/reports/export?type=products&from='.$from.'&to='.$to) ); ?>">Export CSV (Products)</a>
    </form>

    <div style="max-width:1100px">
      <canvas id="mdfsChart" height="80"></canvas>
    </div>

    <h2 style="margin-top:24px">Top Slots theo doanh thu</h2>
    <table class="widefat striped">
      <thead><tr><th>#</th><th>Slot</th><th>Orders</th><th>Items</th><th>Revenue</th></tr></thead>
      <tbody id="mdfsSlots"></tbody>
    </table>

    <h2 style="margin-top:24px">Top Sản phẩm</h2>
    <table class="widefat striped">
      <thead><tr><th>#</th><th>Product</th><th>Orders</th><th>Qty</th><th>Revenue</th></tr></thead>
      <tbody id="mdfsProducts"></tbody>
    </table>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
  (async function(){
    const from = <?php echo json_encode($from); ?>;
    const to   = <?php echo json_encode($to); ?>;
    const url  = <?php echo json_encode( rest_url('mdfs/v1/reports/summary') ); ?> + '?from='+from+'&to='+to;

    const res = await fetch(url, { headers:{ 'X-WP-Nonce': <?php echo json_encode($nonce); ?> } });
    const data = await res.json();

    // Line chart Revenue by day
    const ctx = document.getElementById('mdfsChart');
    new Chart(ctx, {
      type: 'line',
      data: { labels: data.series.labels, datasets: [{ label:'Revenue', data: data.series.revenue }]},
      options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
    });

    // Slots table
    const sBody = document.getElementById('mdfsSlots');
    sBody.innerHTML = data.slots.map((r,i)=>`<tr>
      <td>${i+1}</td>
      <td><a href="<?php echo admin_url('post.php'); ?>?post=${r.slot_id}&action=edit" target="_blank">${r.title||('Slot #'+r.slot_id)}</a></td>
      <td>${r.orders}</td><td>${r.items}</td><td>${Number(r.revenue).toLocaleString()}</td>
    </tr>`).join('') || '<tr><td colspan="5">Không có dữ liệu</td></tr>';

    // Products table
    const pBody = document.getElementById('mdfsProducts');
    pBody.innerHTML = data.products.map((r,i)=>`<tr>
      <td>${i+1}</td>
      <td><a href="<?php echo admin_url('post.php'); ?>?post=${r.vid||r.pid}&action=edit" target="_blank">${r.name}</a></td>
      <td>${r.orders}</td><td>${r.qty}</td><td>${Number(r.revenue).toLocaleString()}</td>
    </tr>`).join('') || '<tr><td colspan="5">Không có dữ liệu</td></tr>';
  })();
  </script>
  <?php
}
