(function($){
    'use strict';

    function sectostr(time)
    {
        var sec_time = {
            century: 3.1556926 * Math.pow(10,9),
            decade:  315569260,
            year:    31556926,
            month:   26297434,
            week:    604800,
            day:     86400,
            hour:    3600,
            minute:  60,
            second:  1
        };

        var str = "";

        for (var key in sec_time) {
            var seconds = sec_time[ key ];
            if ( seconds > time )
                continue;
            var current_value = Math.floor( parseInt( time / seconds, 10 ) );
            str += current_value + ( ( current_value != 1 ) ? " " + key + "s " : " " + key + " " );
            time %= seconds;
        }
        return str.replace("centurys", "centuries");
    }

    $( function(){

        var output = $('#human-purge-time');
        var input  = $('#http-api-debug-purge-after');
        var links  = $('#purge-time-quick-links');
        var times  = $('#predefined-times option');

        function update_seconds_output(seconds) {
            output.val( seconds ? sectostr( seconds ) : '' );
        }

        $('.body-output').each( function (i, block) {
            hljs.highlightBlock(block);
        } );

        input.on('input', function() {
            update_seconds_output( this.valueAsNumber );
        } );

        update_seconds_output( input.val() );

        times.each( function( index, element ) {

            var seconds = this.value;
            var link = $("<a>" + this.text + "</a>").click( function() {
                input.val( seconds );
                update_seconds_output( seconds );
            } );

            links.append(link);

        } );

    } );

})(jQuery);
