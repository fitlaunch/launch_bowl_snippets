jQuery(document).ready(function($) {
    var userId = ajax_object.user_id;
    console.log('User ID:', userId); // Log the user ID

    // Fetch current selections for the user
    function updateCurrentSelections() {
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_current_selections',
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    var selections = response.data;
                    if (selections.message) {
                        $('#current-selections').hide();
                    } else {
                        $('#current-selection-1 span').text(selections.selection_1);
                        $('#current-selection-2 span').text(selections.selection_2);
                        $('#current-selection-3 span').text(selections.selection_3);
                        $('#current-selection-4 span').text(selections.selection_4);
                        $('#current-selection-5 span').text(selections.selection_5);
                        $('#current-selection-wild span').text(selections.selection_wild);
                        $('#current-selections').show();
                    }
                } else {
                    console.log('Error response:', response);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('AJAX error:', textStatus, errorThrown);
            }
        });
    }

    // Initial load of current selections
    updateCurrentSelections();

    // Enable drag and drop
    $('.name-item').draggable({
        helper: 'clone'
    });

    $('#selections-form input').droppable({
        accept: '.name-item',
        drop: function(event, ui) {
            var name = ui.helper.data('name');
            $(this).val(name);
        }
    });

    // Check if picks are open or closed
    function checkPicksStatus(callback) {
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'check_picks_status'
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data.status, response.data.week_number);
                } else {
                    callback('closed');
                }
            },
            error: function() {
                callback('closed');
            }
        });
    }

    // Disable form inputs and submit button if picks are closed
    function disableForm() {
        $('#selections-form input').prop('disabled', true);
        $('#selections-form button').prop('disabled', true);
    }

    // Enable form inputs and submit button if picks are open
    function enableForm() {
        $('#selections-form input').prop('disabled', false);
        $('#selections-form button').prop('disabled', false);
    }

    // Check picks status on page load
    checkPicksStatus(function(status, weekNumber) {
        if (status === 'closed') {
            disableForm();
        } else {
            enableForm();
        }
    });

    // Prevent duplicate entries and handle form submission
    $('#selections-form').on('submit', function(e) {
        e.preventDefault();
        var selections = [];
        var hasDuplicates = false;
        $('#selections-form input').each(function() {
            var value = $(this).val();
            if (value && selections.indexOf(value) === -1) {
                selections.push(value);
            } else if (value) {
                hasDuplicates = true;
                alert('Duplicate entries are not allowed.');
                return false;
            }
        });

        if (hasDuplicates) {
            return;
        }

        // Check picks status before submitting the form
        checkPicksStatus(function(status, weekNumber) {
            if (status === 'closed') {
                alert('Picks are closed for this week.');
            } else {
                // Submit the form via AJAX
                $.ajax({
                    url: ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'save_selections',
                        user_id: userId,
                        selections: selections
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Selections saved successfully.');
                            // Clear the form
                            $('#selections-form')[0].reset();
                            // Update current selections
                            updateCurrentSelections();
                        } else {
                            alert('Error saving selections.');
                            console.log('Error response:', response);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.log('AJAX error:', textStatus, errorThrown);
                    }
                });
            }
        });
    });

    // Search functionality
    $('#search-box').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.name-item').each(function() {
            var name = $(this).data('name').toLowerCase();
            if (name.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Sort functionality
    $('#sort-options').on('change', function() {
        var sortOption = $(this).val();
        var items = $('.name-item').get();

        items.sort(function(a, b) {
            var nameA = $(a).data('name').toLowerCase();
            var nameB = $(b).data('name').toLowerCase();
            var pointsA = parseFloat($(a).data('points'));
            var pointsB = parseFloat($(b).data('points'));

            if (sortOption === 'az') {
                return nameA.localeCompare(nameB);
            } else if (sortOption === 'za') {
                return nameB.localeCompare(nameA);
            } else if (sortOption === 'pts-high-low') {
                return pointsB - pointsA;
            } else if (sortOption === 'pts-low-high') {
                return pointsA - pointsB;
            }
        });

        $.each(items, function(index, item) {
            $('#names-list').append(item);
        });
    });

    // Set default sort to points high to low
    $('#sort-options').val('pts-high-low').trigger('change');
});