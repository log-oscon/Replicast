(function($) {
  'use strict';

  // Posts list
  $('.wp-list-table.posts tbody tr').each(function() {

    var $row      = $(this);
    var $isRemote = $row.find('.column-replicast a').length > 0;

    if (!$isRemote) {
      return;
    }

    // Remove edit link from taxonomies terms
    $row.find('[class*="column-taxonomy"]').each(function() {
      var $editLinks = $(this).find('a');
      if ($editLinks.length > 0) {
        $editLinks.contents().unwrap();
      }
    });

  });

  // Terms list
  $('.wp-list-table.tags tbody tr').each(function() {

    var $row      = $(this);
    var $isRemote = $row.find('.column-replicast a').length > 0;

    if (!$isRemote) {
      return;
    }

    // Remove checkbox
    $row.find('.check-column').empty();

    // Remove edit link from term
    var $editLink = $row.find('.column-name .row-title');
    if ($editLink.length > 0) {
      $editLink.contents().unwrap();
    }

  });

})(jQuery);
