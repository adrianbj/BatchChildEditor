function getUrlVars(url) {
    var vars = {};
    var parts = url.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
        vars[key] = value;
    });
    return vars;
}


function batchChildTableDialog() {

    var $a = $(this);
    var url = $a.attr('data-url')
    var closeOnSave = true;
    var $iframe = pwModalWindow(url, {}, 'large');

    var dialogPageID = 0;

    $iframe.load(function () {

        var buttons = [];
        var pid = getUrlVars(url)["id"];
        var langid = getUrlVars(url)["lang"];
        var $icontents = $iframe.contents();
        var initTemplate = $icontents.find('#template option:selected').text();
        var n = 0;

        dialogPageID = $icontents.find('#Inputfield_id').val(); // page ID that will get added if not already present

        // hide things we don't need in a modal context
        //$icontents.find('#breadcrumbs ul.nav, #Inputfield_submit_save_field_copy').hide();

        closeOnSave = $icontents.find('#ProcessPageAdd').size() == 0;

        // copy buttons in iframe to dialog
        $icontents.find("#content form button.ui-button[type=submit], button.ui-button[name=submit_delete]").each(function () {
            var $button = $(this);
            var text = $button.text();
            var skip = false;
            // avoid duplicate buttons
            for (i = 0; i < buttons.length; i++) {
                if (buttons[i].text == text || text.length < 1) skip = true;
            }
            // ignore Move to Trash button because we need to add click event to it separately
            if (text == 'Move to Trash') skip = true;

            if (!skip) {
                buttons[n] = {
                    'text': text,
                    'class': ($button.is('.ui-priority-secondary') ? 'ui-priority-secondary' : ''),
                    'click': function () {
                        $button.click();
                        //if template has changed, then don't close on save as may need to accept confirmation of change
                        if (closeOnSave && initTemplate == $icontents.find('#template option:selected').text()) setTimeout(function () {
                            $iframe.dialog('close');
                            //var titleFieldId = langid ? '#Inputfield_title__' + langid : '#Inputfield_title';
                            //var titleVal = $icontents.find(titleFieldId).val();
                            var titleVal = $icontents.find('#Inputfield_title').val()
                            $('#' + pid).val(titleVal);
                            $('#' + pid).attr('placeholder', titleVal);
                            var nameFieldId = langid ? '#Inputfield__pw_page_name' + langid : '#Inputfield__pw_page_name';
                            var nameVal = $icontents.find(nameFieldId).val();
                            $('#name_' + pid).text(nameVal);
                            if (langid) $('input[id=langActiveStatus_' + pid + ']').prop('checked', $icontents.find('input[name=status' + langid + ']').is(':checked'));
                            //version for when template is changeable and in a select dropdown
                            $('select[id=template_' + pid).val($icontents.find('#template option:selected').val());
                            //version for when edit mode doesn't allow changing template, so it's just in a span
                            $('span#template_' + pid).text($icontents.find('#template option:selected').text());
                            $('input[id=hiddenStatus_' + pid + ']').prop('checked', $icontents.find('#Inputfield_status_1024').is(':checked'));
                            $('input[id=unpublishedStatus_' + pid + ']').prop('checked', $icontents.find('#Inputfield_status_2048').is(':checked'));
                            //if the user clicks the Publish button rather than unchecking the "Unpublished" checkbox
                            if (text == 'Publish') $('input[id=unpublishedStatus_' + pid + ']').prop('checked', false);
                        }, 500);
                        closeOnSave = true; // only let closeOnSave happen once
                    }
                };
                n++;
            }
            ;

            // don't hide "Move to Trash" button
            if (text == 'Move to Trash') {
                $button.on("click", function () {
                    //delete page from table row if page deleted in modal
                    if ($icontents.find('input[id=delete_page][value=' + pid + ']').is(':checked')) {
                        $('tr.pid_' + pid).remove();
                    }
                    $iframe.dialog('close');
                });
            }
            else {
                // hide original versions of buttons
                $button.hide();
            }

        });

        $iframe.setButtons(buttons);

    });

    return false;
}


function batchChildTableSortable($table) {
    if (!$table.is("tbody")) $table = $table.find("tbody");
    $table.sortable({
        axis: 'y',
        handle: '.InputfieldChildTableRowSortHandle'
    });
}

$(document).ready(function () {

    $(document).one('mouseover', '.batchChildTableContainer table', function () {

        $('.batchChildTableContainer table > tbody > tr').each(function () {

            var row = $(this),
                bcePageNameCell = $(this).find('.bcePageName[data-langinfo]');

            if (!bcePageNameCell.length) return false;

            var cellCount = $(this).find('td').length - 1,
                multilangPageInfo = bcePageNameCell.attr('data-langinfo');

            row.after('<tr class="bceLangInfoRow"><td></td><td colspan="' + cellCount + '"><div>' + multilangPageInfo + '</div></td></tr>');
            bcePageNameCell.parent('span').css('white-space', 'nowrap')
                .prepend('<i class="bceTogglePageInfo fa fa-plus-square-o">');
        });

        $('.bceTogglePageInfo').on('click', function () {
            $('.pageinfo-visible').not($(this).parents('.pageinfo-visible').first()).removeClass('pageinfo-visible');
            $(this).parents('tr').first().toggleClass('pageinfo-visible');
        })
    });

    //csv export
    //$('.Inputfield_iframe').hide();
    $(document).on('click', '.children_export_csv', function () {
        $('#download').attr('src', config.urls.admin +
            "setup/children-csv-export/?pid=" +
            $(this).attr('data-pageid') +
            "&pti=" + ($("#Inputfield_pagesToInclude").val() ? $("#Inputfield_pagesToInclude").val() : $("#Inputfield_pagesToInclude").val()) +
            "&fns=" + ($("#Inputfield_userExportFields").val() ? $("#Inputfield_userExportFields").val() : $("#Inputfield_exportFields").val()) +
            "&cs=" + $("#Inputfield_export_column_separator").val() +
            "&ce=" + $("#Inputfield_export_column_enclosure").val() +
            "&ext=" + $("#Inputfield_export_extension").val() +
            "&nfr=" + ($("#Inputfield_export_names_first_row").is(':checkbox') ? $("#Inputfield_export_names_first_row").attr('checked') : $("#Inputfield_export_names_first_row").val()) +
            "&mvs=" + $("#Inputfield_export_multiple_values_separator").val() +
            "&fe=" + ($("#Inputfield_format_export").is(':checkbox') ? $("#Inputfield_format_export").attr('checked') : $("#Inputfield_format_export").val())
        );
        return false;
    });


    /**
     * Add toggle controls to column headers (check/uncheck all items in a column)
     *
     * @author tpr
     * @updated 2015-09-15
     */

    var bce_adminDataTableSelector = '.batchChildTableContainer .AdminDataTable',
        bce_columnControlClass = 'bce-column-toggle',
        bce_allowedColumnControls = ['input.langActiveStatus', 'input.hiddenStatus', 'input.unpublishedStatus', 'i.InputfieldChildTableRowDeleteLink'],
        bce_toggleControl = '<input type="checkbox" class="' + bce_columnControlClass + '" style="position: relative; top: 2px; margin-right: 4px;" />',
        bce_controlEventType = 'change',
        bce_deletedRowClass = 'InputfieldChildTableRowDeleted',
        bce_isColumnControlsAdded = false;

    $(document).one('mouseover', bce_adminDataTableSelector, function () {
        addBceColumnControls();
    });

    /**
     * Set column header checkbox state based on all items of the column.
     *
     * @param $obj jQuery object
     */
    function setColumnControlStates($obj) {

        var elem = $obj.is('input') ? 'input' : 'i',
            index = $obj.parent('td, th').index(),
            columnControl = $(bce_adminDataTableSelector + ' th:eq(' + index + ') input'),
            columnItems = $(bce_adminDataTableSelector).find('td:nth-child(' + parseInt(index + 1) + ')'),
            checkedItems = (elem == 'input') ? columnItems.find(':checked') : $(bce_adminDataTableSelector).find('.' + bce_deletedRowClass),
            allItems = columnItems.find(elem);

        if (checkedItems.length !== 0 && checkedItems.length === allItems.length) {
            columnControl.prop('checked', 1);
        } else {
            columnControl.prop('checked', 0);
        }
    }

    /**
     * Add control toggle checkboxes to BCE table.
     *
     * @returns {boolean}
     */
    function addBceColumnControls() {

        if (bce_isColumnControlsAdded) {
            return false;
        }

        if ($(bce_adminDataTableSelector).length === 0) {
            return false;
        }

        // do not add controls if there is no more than 1 row
        if ($(bce_adminDataTableSelector + ' tbody tr').length <= 1) {
            return false;
        }

        //$(bce_adminDataTableSelector + ' tbody').on('click', 'input[type="checkbox"], i.InputfieldChildTableRowDeleteLink', function () {
        $(bce_adminDataTableSelector + ' tbody').on('click', 'input[type="checkbox"]', function () {
            setColumnControlStates($(this));
        });

        // add new controls
        for (var i = 0; i < bce_allowedColumnControls.length; i++) {

            var currentControl = bce_allowedColumnControls[i];

            // skip non-existing elements
            if (!$(currentControl).length) {
                continue;
            }

            // get index of first checkbox in the first row
            var index = $(bce_adminDataTableSelector + ' ' + currentControl + ':eq(0)').parent().index();

            // do the add
            $(bce_adminDataTableSelector + ' th:eq(' + index + ')').prepend($(bce_toggleControl));

            // set initial checkbox states
            setColumnControlStates($(bce_adminDataTableSelector + ' th:eq(' + index + ') input'));

            // add event
            addColumnControlEvent(bce_adminDataTableSelector, currentControl, index);
        }

        // disable thead break to multiline
        $(bce_adminDataTableSelector + ' thead').css('white-space', 'nowrap');

        bce_isColumnControlsAdded = true;

        return true;
    }


    /**
     * Add event on column toggle checkboxes.
     *
     * @param bce_adminDataTableSelector
     * @param currentControl
     * @param index
     */
    function addColumnControlEvent(bce_adminDataTableSelector, currentControl, index) {

        var currentColumnControlSelector = bce_adminDataTableSelector + ' thead th:eq(' + index + ') .' + bce_columnControlClass;

        $(currentColumnControlSelector).on(bce_controlEventType, function () {

            var currentColumnControl = $(currentColumnControlSelector),
                toggleState = currentColumnControl.is(':checked');

            $(bce_adminDataTableSelector + ' tbody tr').each(function () {

                var currentRow = $(this),
                    currentItem = currentRow.find('td:eq(' + index + ') ' + currentControl);

                // toggle checkboxes state or trigger clicks
                if (currentItem.is('input')) {
                    currentItem.prop('checked', toggleState);

                } else if (currentItem.is('i')) {
                    if (toggleState) {
                        if (!currentRow.hasClass(bce_deletedRowClass)) {
                            currentItem.trigger('bce-delete-row');
                        }
                    } else {
                        if (currentRow.hasClass(bce_deletedRowClass)) {
                            currentItem.trigger('bce-delete-row');
                        }
                    }
                }
            });
        });
    }

    // End of adding toggle controls to column headers.


    $(document).on('click', '.batchChildTableEdit', batchChildTableDialog);

    var i = 0;
    $(document).on('click', 'button.InputfieldChildTableAddRow', function () {
        var c = 1;
        i++;
        var $table = $(this).closest('.Inputfield').find('table.bceEditTable');
        var $tbody = $table.find('tbody:first');
        var numRows = $tbody.children('tr').size();
        var $row = $tbody.children(":first").clone(true);
        var $titleCell = $row.find("td:eq(" + (c++) + ")").find(':input');
        $titleCell.attr('placeholder', ''); // empty the title placeholder text
        $row.find("td:eq(" + (c++) + ")").html(''); //empty the name cell

        var colCount = 0;
        $row.find("td").each(function () {
            colCount++;
        });
        if (colCount == 10) {
            $row.find("td:eq(" + (c++) + ")").find(':checkbox').prop('checked', false); //uncheck active checkbox
        }

        $row.find("td:eq(" + (c++) + ")").html($('#defaultTemplates').html()); //set template data
        $row.find("td:eq(" + (c++) + ")").find(':checkbox').prop('checked', false); //uncheck hidden checkbox
        $row.find("td:eq(" + (c++) + ")").find(':checkbox').prop('checked', false); //uncheck unpublished checkbox
        $row.find("td:eq(" + (c++) + ")").html(''); //empty the view button cell
        $row.find("td:eq(" + (c++) + ")").html(''); //empty the edit button cell
        $row.find("td:eq(" + (c++) + ")").html(''); //empty the delete button cell

        //in case the first row was set for deletion - the new row, cloned from this, would also be set for deletion, so need to remove class and restore opacity
        $row.removeClass('InputfieldChildTableRowDeleted');
        $row.css('opacity', 1.0);

        $row.find(":input").each(function () {
            var $input = $(this);
            if ($($input).is("select")) {
                $input.attr("name", "templateId[new_" + i + "]");
            }
            else if ($($input).hasClass('langActiveStatus')) {
                $input.attr("name", "langActiveStatus[new_" + i + "]");
            }
            else if ($($input).hasClass('hiddenStatus')) {
                $input.attr("name", "hiddenStatus[new_" + i + "]");
            }
            else if ($($input).hasClass('unpublishedStatus')) {
                $input.attr("name", "unpublishedStatus[new_" + i + "]");
            }
            else if ($input.is('.InputfieldChildTableRowSort')) $input.val(numRows);
            else {
                $input.attr("name", "individualChildTitles[new_" + i + "]");
                $input.attr('value', '');
                $input.attr('id', '');
            }
        });

        $tbody.append($row);
        $table.show();
        $titleCell.focus();
        return false;
    });

    // make rows sortable - trigger this on first ("one") mouseover of a sort handle in case BCE fieldset is being opened via AJAX
    $(document).one('mouseover', '.InputfieldChildTableRowSortHandle', function () {
        $("table.AdminDataTable").each(function () {
            batchChildTableSortable($(this));
        });
    });

    // row deletion
    var deleteIds;
    $(document).on('click bce-delete-row', '.InputfieldChildTableRowDeleteLink', function () {
        var $row = $(this).closest('tr');
        var $input = $('.InputfieldChildTableRowDelete');

        if ($row.is('.InputfieldChildTableRowDeleted')) {
            // undelete
            $row.removeClass('InputfieldChildTableRowDeleted');
            $row.css('opacity', 1.0);
            deleteIds = $input.val().replace($row.find("td:eq(1)").find("input").attr("id") + ',', '');
            $input.val(deleteIds);

        } else {
            // delete
            $row.addClass('InputfieldChildTableRowDeleted');
            $row.css('opacity', 0.3);
            deleteIds = $input.val() + $row.find("td:eq(1)").find("input").attr("id") + ',';
            $input.val(deleteIds);
        }

        setColumnControlStates($(this));
    });

    //Add or remove "Title" label from Text/Paste CSV textarea if user changes ignore first row setting
    $(document).on('change', '#Inputfield_userIgnoreFirstRow', function () {
        var initialAddText = $('textarea[name=childPagesAdd]').val();
        var initialUpdateText = $('textarea[name=childPagesUpdate]').val();
        var initialReplaceText = $('textarea[name=childPagesReplace]').val();
        if ($(this).is(':checked')) {
            if ($('textarea[name=childPagesAdd]').length) $('textarea[name=childPagesAdd]').val("Title\n" + initialAddText);
            if ($('textarea[name=childPagesUpdate]').length) $('textarea[name=childPagesUpdate]').val("Title\n" + initialUpdateText);
            if ($('textarea[name=childPagesReplace]').length) $('textarea[name=childPagesReplace]').val("Title\n" + initialReplaceText);
        }
        else {
            if ($('textarea[name=childPagesAdd]').length) $('textarea[name=childPagesAdd]').val(removeFirstLine(initialAddText));
            if ($('textarea[name=childPagesUpdate]').length) $('textarea[name=childPagesUpdate]').val(removeFirstLine(initialUpdateText));
            if ($('textarea[name=childPagesReplace]').length) $('textarea[name=childPagesReplace]').val(removeFirstLine(initialReplaceText));
        }
    });

});

function removeFirstLine(text) {
    // break the textblock into an array of lines
    var lines = text.split('\n');
    // remove one line, starting at the first position
    lines.splice(0, 1);
    // join the array back into a single string
    return lines.join('\n');
}
