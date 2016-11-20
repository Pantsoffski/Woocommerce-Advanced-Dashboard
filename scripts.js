jQuery(function () {
    var date = new Date();
    var firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
//    var lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0); //In Case I need it
    jQuery("#startdate").datepicker({dateFormat: 'dd/mm/yy'});
    jQuery("#startdate").datepicker("setDate", firstDay);
    jQuery("#enddate").datepicker({dateFormat: 'dd/mm/yy'});
    jQuery("#enddate").datepicker("setDate", new Date());
});

function change_data_ad() {
    var gif_dir = jQuery("#gifDir").val();
    jQuery('#advanced-dashboard-ul').html("<center><p><img src='" + gif_dir + "' width='50%' /></p></center>");
//  jQuery('#advanced-dashboard-ul').load("/blabla/blabla.html");
}