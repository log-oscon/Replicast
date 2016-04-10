(function($) {
  'use strict';

  // Terms list
  $('.wp-list-table.tags tbody tr').each(function() {

    var $row      = $(this);
    var $isRemote = $row.find('.column-replicast a').length > 0;

    if (!$isRemote) {
      return;
    }

    // Remove checkbox
    $row.find('.check-column').empty();

    // Remove edit link
    var $editLink = $row.find('.column-name .row-title');
    if ($editLink.length > 0) {
      $editLink.contents().unwrap();
    }

  });

})(jQuery);
