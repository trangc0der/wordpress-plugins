<?php
if ( ! defined('ABSPATH') ) exit;
add_action('wp_footer', function(){ ?>
<script>
document.addEventListener('mouseover', function(e){
  var a=e.target.closest('a'); if(!a||!a.href) return;
  if(a.dataset.mdfsPrefetched) return; a.dataset.mdfsPrefetched=1;
  var l=document.createElement('link'); l.rel='prefetch'; l.href=a.href; document.head.appendChild(l);
});
</script>
<?php });
