jQuery(document).ready(function($){
    $('#kmi_rental_start_date').datepicker();
    $('#kmi_rental_birthdate').datepicker();
    $('#kmi_rental_current_date_in').datepicker({
        defaultDate: '+1w',
        changeMonth: true,
        onClose: function(selectedDate){
            $('#kmi_rental_current_date_out').datepicker('option', 'minDate', selectedDate);
        }
    });
    $('#kmi_rental_current_date_out').datepicker({
        defaultDate: '+1w',
        changeMonth: true,
        onClose: function(selectedDate){
            $('#kmi_rental_current_date_in').datepicker('option', 'maxDate', selectedDate);
        }
    });
    $('#kmi_rental_previous_date_in').datepicker({
        defaultDate: '+1w',
        changeMonth: true,
        onClose: function(selectedDate){
            $('#kmi_rental_previous_date_out').datepicker('option', 'minDate', selectedDate);
        }
    });
    $('#kmi_rental_previous_date_out').datepicker({
        defaultDate: '+1w',
        changeMonth: true,
        onClose: function(selectedDate){
            $('#kmi_rental_previous_date_in').datepicker('option', 'maxDate', selectedDate);
        }
    });
    $('#kmi_rental_occupation_start_date_1').datepicker({
        defaultDate: '+1w',
        changeMonth: true,
        onClose: function(selectedDate){
            $('#kmi_rental_occupation_end_date_1').datepicker('option', 'minDate', selectedDate);
        }
    });
    $('#kmi_rental_occupation_end_date_1').datepicker({
        defaultDate: '+1w',
        changeMonth: true,
        onClose: function(selectedDate){
            $('#kmi_rental_occupation_start_date_1').datepicker('option', 'maxDate', selectedDate);
        }
    });
    $('#kmi_rental_occupation_start_date_2').datepicker({
        defaultDate: '+1w',
        changeMonth: true,
        onClose: function(selectedDate){
            $('#kmi_rental_occupation_end_date_2').datepicker('option', 'minDate', selectedDate);
        }
    });
    $('#kmi_rental_occupation_end_date_2').datepicker({
        defaultDate: '+1w',
        changeMonth: true,
        onClose: function(selectedDate){
            $('#kmi_rental_occupation_start_date_2').datepicker('option', 'maxDate', selectedDate);
        }
    });
});