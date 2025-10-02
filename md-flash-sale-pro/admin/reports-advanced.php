<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=mdfs_slot',
        __('Flash Sale Reports Advanced','mdfs-rt'),
        __('Reports (Advanced)','mdfs-rt'),
        'manage_options',
        'mdfs_reports_advanced',
        'mdfs_reports_advanced_page'
    );
});

function mdfs_reports_advanced_page() {
    $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d', strtotime('-14 days'));
    $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : date('Y-m-d');
    $nonce = wp_create_nonce('wp_rest');
    ?>
    <div class="wrap">
        <h1>📊 Flash Sale Reports (Advanced)</h1>
        <form method="get" style="margin:12px 0">
            <input type="hidden" name="post_type" value="mdfs_slot"/>
            <input type="hidden" name="page" value="mdfs_reports_advanced"/>
            <label>Từ ngày: <input type="date" name="from" value="<?php echo esc_attr($from); ?>"/></label>
            <label>Đến ngày: <input type="date" name="to" value="<?php echo esc_attr($to); ?>"/></label>
            <button class="button button-primary">Xem</button>
            <a class="button" href="<?php echo esc_url(rest_url("mdfs/v1/report/export?type=slots&from=$from&to=$to")); ?>" target="_blank">Export CSV (Slots)</a>
            <a class="button" href="<?php echo esc_url(rest_url("mdfs/v1/report/export?type=products&from=$from&to=$to")); ?>" target="_blank">Export CSV (Products)</a>
        </form>

        <div id="mdfs-summary" style="display:flex;gap:20px;margin-bottom:20px">
            <div class="card"><strong id="sum-slots">0</strong><br>Slots</div>
            <div class="card"><strong id="sum-products">0</strong><br>Products</div>
            <div class="card"><strong id="sum-orders">0</strong><br>Orders</div>
            <div class="card"><strong id="sum-revenue">0</strong><br>Revenue</div>
        </div>

        <canvas id="mdfsChart" height="80"></canvas>

        <h2>Top Slots</h2>
        <table class="widefat striped"><thead><tr><th>#</th><th>Slot</th><th>Revenue</th></tr></thead><tbody id="mdfsSlots"></tbody></table>

        <h2>Top Products</h2>
        <table class="widefat striped"><thead><tr><th>#</th><th>Product</th><th>Sold</th><th>Revenue</th></tr></thead><tbody id="mdfsProducts"></tbody></table>

        <h2>⚠️ Low Stock</h2>
        <table class="widefat striped"><thead><tr><th>#</th><th>Slot</th><th>Product</th><th>Remain</th></tr></thead><tbody id="mdfsLow"></tbody></table>
    </div>

    <style>
        .card{padding:12px 20px;background:#fff;border:1px solid #ddd;border-radius:6px;flex:1;text-align:center;}
        .card strong{font-size:20px;display:block;margin-bottom:4px}
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    (async function(){
        const headers={'X-WP-Nonce':<?php echo json_encode($nonce); ?>};
        const from=<?php echo json_encode($from); ?>;
        const to=<?php echo json_encode($to); ?>;

        // Summary
        let s=await fetch(<?php echo json_encode(rest_url('mdfs/v1/report/summary')); ?>+'?from='+from+'&to='+to,{headers});
        let sum=await s.json();
        document.getElementById('sum-slots').textContent=sum.total_slots;
        document.getElementById('sum-products').textContent=sum.total_products;
        document.getElementById('sum-orders').textContent=sum.total_orders;
        document.getElementById('sum-revenue').textContent=Number(sum.total_revenue).toLocaleString();

        // Trend chart
        let t=await fetch(<?php echo json_encode(rest_url('mdfs/v1/report/trend')); ?>+'?from='+from+'&to='+to,{headers});
        let trend=await t.json();
        new Chart(document.getElementById('mdfsChart'),{
            type:'line',
            data:{labels:trend.labels,datasets:[{label:'Revenue',data:trend.revenue,borderColor:'#f33',fill:false}]}
        });

        // Top slots
        let r=await fetch(<?php echo json_encode(rest_url('mdfs/v1/report/top-slots')); ?>+'?from='+from+'&to='+to,{headers});
        let slots=await r.json();
        document.getElementById('mdfsSlots').innerHTML=slots.map((x,i)=>`<tr><td>${i+1}</td><td>${x.title}</td><td>${Number(x.revenue).toLocaleString()}</td></tr>`).join('');

        // Top products
        let p=await fetch(<?php echo json_encode(rest_url('mdfs/v1/report/top-products')); ?>+'?from='+from+'&to='+to,{headers});
        let products=await p.json();
        document.getElementById('mdfsProducts').innerHTML=products.map((x,i)=>`<tr><td>${i+1}</td><td>${x.title}</td><td>${x.sold}</td><td>${Number(x.revenue).toLocaleString()}</td></tr>`).join('');

        // Low stock
        let l=await fetch(<?php echo json_encode(rest_url('mdfs/v1/report/low-stock')); ?>,{headers});
        let lows=await l.json();
        document.getElementById('mdfsLow').innerHTML=lows.map((x,i)=>`<tr><td>${i+1}</td><td>${x.slot_title}</td><td>${x.title}</td><td>${x.remain}</td></tr>`).join('');
    })();
    </script>
    <?php
}
