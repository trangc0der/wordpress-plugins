(function($){
  $(function(){
    var labels = ['Sản phẩm','Biến thể (ID)','Giá KM','Giá gốc','Quota','Còn lại','Badges',''];
    $('#mdfs-products-table tbody tr').each(function(){
      $(this).find('td').each(function(i){
        if(!this.hasAttribute('data-label')) this.setAttribute('data-label', labels[i]||'');
      });
    });
  });
})(jQuery);
