$(document).ready(function () {

    $('.tool-card').on('mouseenter', function () {
        $(this).addClass('active');
    });

    $('.tool-card').on('mouseleave', function () {
        $(this).removeClass('active');
    });

});