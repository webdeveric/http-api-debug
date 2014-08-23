(function($){
    'use strict';

    $( function(){

        $('.response-body').each( function (i, block) {
            hljs.highlightBlock(block);
        } );

    } );

})(jQuery);
