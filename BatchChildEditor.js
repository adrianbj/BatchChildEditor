function getUrlVars(url) {
    var vars = {};
    var parts = url.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
        vars[key] = value;
    });
    return vars;
}


function childChildTableDialog() {

    var $a = $(this);
    var url = $a.attr('data-url');
    var title = $a.attr('data-title');
    var closeOnSave = true;
    var pid = getUrlVars(url)["id"];
    var $iframe = $('<iframe id="childEditFrame_'+pid+'" frameborder="0" src="' + url + '"></iframe>');
    var windowWidth = $(window).width()-100;
    var windowHeight = $(window).height()-220;
    var $container = $('#'+pid);
    var dialogPageID = 0;

    var $dialog = $iframe.dialog({
        modal: true,
        height: windowHeight,
        width: windowWidth,
        position: [50,49],
        close: function(event, ui) {
            if(dialogPageID > 0) {
                $container.val($("#childEditFrame_"+pid).contents().find('#Inputfield_title').val());
                $container.effect('highlight', 1000);
            }
        }
    }).width(windowWidth).height(windowHeight);

    $iframe.load(function() {

        var buttons = [];
        var $icontents = $iframe.contents();
        var n = 0;
        var title = $icontents.find('title').text();

        dialogPageID = $icontents.find('#Inputfield_id').val(); // page ID that will get added if not already present

        // set the dialog window title
        $dialog.dialog('option', 'title', title);

        // hide things we don't need in a modal context
        //$icontents.find('#wrap_Inputfield_template, #wrap_template, #wrap_parent_id').hide();
        //$icontents.find('#breadcrumbs ul.nav, #_ProcessPageEditDelete, #_ProcessPageEditChildren').hide();

        closeOnSave = $icontents.find('#ProcessPageAdd').size() == 0;

        // copy buttons in iframe to dialog
        $icontents.find("#content form button.ui-button[type=submit]").each(function() {
            var $button = $(this);
            var text = $button.text();
            var skip = false;
            // avoid duplicate buttons
            for(i = 0; i < buttons.length; i++) {
                if(buttons[i].text == text || text.length < 1) skip = true;
            }
            if(!skip) {
                buttons[n] = {
                    'text': text,
                    'class': ($button.is('.ui-priority-secondary') ? 'ui-priority-secondary' : ''),
                    'click': function() {
                        $button.click();
                        if(closeOnSave) setTimeout(function() {
                            $dialog.dialog('close');
                        }, 500);
                        closeOnSave = true; // only let closeOnSave happen once
                    }
                };
                n++;
            };
            $button.hide();
        });

        if(buttons.length > 0) $dialog.dialog('option', 'buttons', buttons);
        $dialog.width(windowWidth).height(windowHeight);

    });

    return false;
}


function childChildTableSortable($table) {
    if(!$table.is("tbody")) $table = $table.find("tbody");
    $table.sortable({
        axis: 'y',
        handle: '.InputfieldChildTableRowSortHandle'
    });
}

$(document).ready(function() {

    //csv export
    $('.Inputfield_iframe').hide();
    $('.Inputfield_csv_settings').hide();
    $(document).on('click', '.children_export_csv', function(){
        $('#download').attr('src', config.urls.admin+
            "setup/children-csv-export/?pid="+
            $(this).attr('data-pageid')+
            "&fns="+($("#Inputfield_userExportFields").val() ? $("#Inputfield_userExportFields").val() : $("#Inputfield_exportFields").val())+
            "&cs="+$("#Inputfield_export_column_separator").val()+
            "&ce="+$("#Inputfield_export_column_enclosure").val()+
            "&ext="+$("#Inputfield_export_extension").val()+
            "&nfr="+($("#Inputfield_export_names_first_row").is(':checkbox') ? $("#Inputfield_export_names_first_row").attr('checked') : $("#Inputfield_export_names_first_row").val())+
            "&mvs="+$("#Inputfield_multiple_values_separator").val()
        );
        return false;
    });



    $(document).on('click', '.childChildTableEdit', childChildTableDialog);

    var i=0;
    $('button.InputfieldChildTableAddRow').click(function() {
        i++;
        var $table = $(this).closest('.Inputfield').find('table');
        var $tbody = $table.find('tbody');
        var numRows = $tbody.children('tr').size();
        var $row = $tbody.children(":first").clone(true);

        $row.find("td:eq(2)").html(''); //empty the name cell
        $row.find("td:eq(3)").html($('#defaultTemplates').html()); //set template data
        $row.find("td:eq(4)").find(':checkbox').prop('checked', false); //uncheck hidden checkbox
        $row.find("td:eq(5)").find(':checkbox').prop('checked', false); //uncheck unpublished checkbox
        $row.find("td:eq(6)").html(''); //empty the view button cell
        $row.find("td:eq(7)").html(''); //empty the edit button cell
        $row.find("td:eq(8)").html(''); //empty the delete button cell

        //in case the first row was set for deletion - the new row, cloned from this, would also be set for deletion, so need to remove class and restore opacity
        $row.removeClass('InputfieldChildTableRowDeleted');
        $row.css('opacity', 1.0);

        $row.find(":input").each(function() {
            var $input = $(this);
            if($($input).is("select")) {
                $input.attr("name", "templateId[new_"+i+"]");
            }
            else if($($input).hasClass('hiddenStatus')) {
                $input.attr("name", "hiddenStatus[new_"+i+"]");
            }
            else if($($input).hasClass('unpublishedStatus')) {
                $input.attr("name", "unpublishedStatus[new_"+i+"]");
            }
            else if($input.is('.InputfieldChildTableRowSort')) $input.val(numRows);
            else {
                $input.attr("name", "individualChildTitles[new_"+i+"]");
                $input.attr('value', '');
                $input.attr('id', '');
            }
        });

        $tbody.append($row);
        $table.show();
        $row.find(":input").focus();
        return false;
    });

    $("table.AdminDataTableSortable").each(function() {
        childChildTableSortable($(this));
    });

    // row deletion
    var deleteIds;
    $(document).on('click', '.InputfieldChildTableRowDeleteLink', function() {
        var $row = $(this).closest('tr');
        var $input = $('.InputfieldChildTableRowDelete');

        if($row.is('.InputfieldChildTableRowDeleted')) {
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
    });

    //Add or remove "Title" label from Text/Paste CSV textarea if user changes ignore first row setting
     $('#Inputfield_userIgnoreFirstRow').change(function() {
        var initialAddText = $('textarea[name=childPagesAdd]').val();
        var initialUpdateText = $('textarea[name=childPagesUpdate]').val();
        var initialReplaceText = $('textarea[name=childPagesReplace]').val();
        if($(this).is(':checked')){
            if($('textarea[name=childPagesAdd]').length) $('textarea[name=childPagesAdd]').val("Title\n" + initialAddText);
            if($('textarea[name=childPagesUpdate]').length) $('textarea[name=childPagesUpdate]').val("Title\n" + initialUpdateText);
            if($('textarea[name=childPagesReplace]').length) $('textarea[name=childPagesReplace]').val("Title\n" + initialReplaceText);
        }
        else {
            if($('textarea[name=childPagesAdd]').length) $('textarea[name=childPagesAdd]').val(removeFirstLine(initialAddText));
            if($('textarea[name=childPagesUpdate]').length) $('textarea[name=childPagesUpdate]').val(removeFirstLine(initialUpdateText));
            if($('textarea[name=childPagesReplace]').length) $('textarea[name=childPagesReplace]').val(removeFirstLine(initialReplaceText));
        }
    });

});

function removeFirstLine(text) {
    // break the textblock into an array of lines
    var lines = text.split('\n');
    // remove one line, starting at the first position
    lines.splice(0,1);
    // join the array back into a single string
    return lines.join('\n');
}