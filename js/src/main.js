(function($){
    'use strict';

    $( function(){

        $('.body-output').each( function (i, block) {
            hljs.highlightBlock(block);
        } );

    } );

})(jQuery);
