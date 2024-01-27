(function ($, Drupal) {
    Drupal.behaviors.updateStatistics = {
      attach: function (context, settings) {
        setInterval(function () {
          $.ajax({
            url: '/admin/config/ajax-statistics',
            method: 'GET',
            success: function (data) {
                const targets = [
                    'total-products',
                    'total-files',
                    'total-logs',
                    'total-lines',
                    'total-queue-lines',
                    'total-queue-lines-products',
                    'total-queue-lines-editorials',
                    'total-queue-lines-authors',
                    'latest-queue-lines-gp4a',
                    'latest-queue-lines-auta',
                    'latest-queue-lines-1la',
                    'total-queue-products',
                    'queued-filename',
                    'geslib-log-logged',
                    'geslib-log-queued',
                    'geslib-log-dilve',
                    'geslib-log-processed',
                ];
                targets.forEach( target => {
                    let $element = $( `[data-target="${target}"]` );
                    let $li = $element.closest( 'li' );
                    $li.addClass( 'darker-background' );
                    $element.fadeOut( 400, function(){
                        $element.text( data[target] );
                        $element.fadeIn( 400 );
                        $li.removeClass( 'darker-background' );
                    } );
                } );

                $('[data-container="geslib_products"] #outputDiv').html(data['geslib-latest-logs']);
              // ... Update other elements
            }
          });
        }, 10000); // Update every 10 seconds
      }
    };
  })(jQuery, Drupal);
