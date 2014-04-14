$(document).on("click", ".batch_add", function() {
    $(".batch_add").before('<li class="Inputfield InputfieldText Inputfield_individualChildTitles[] InputfieldColumnWidthFirst" id="wrap_Inputfield_individualChildTitles[]">' +
                                '<label class="InputfieldHeader InputfieldStateToggle" for="Inputfield_individualChildTitles[]"><i class="toggle-icon fa fa-angle-down"></i>&nbsp;</label>' +
                                '<div class="InputfieldContent">' +
                                    '<input id="Inputfield_individualChildTitles[]" class="InputfieldMaxWidth" name="individualChildTitles[]" value="" type="text" maxlength="2048">' +
                                '</div>' +
                            '</li>');
});