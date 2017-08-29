BatchChildEditor
================

Processwire module for quick batch creation (titles only or CSV import for other fields), editing, sorting, deletion, and CSV export of all children under a given page.

This module adds a variety of importing, editing, and exporting tools. The interface can be added to the Children Tab, or in a new dedicated tab, or placed inline with other fields in the Content tab.

#### Modes
1. Lister - Embeds a customized Lister interface. Installation of ListerPro will allow inline ajax editing of displayed fields.
2. Edit - Allows you to quidkly rename existing child pages, add new child pages, sort, and delete pages. It also has a modal edit link from the page name to allow easy access to edit all the fields on the page.
3. Add - Adds newly entered page titles as child pages to the list of existing siblings. You could create a list of pages in Word or whatever and just paste them in here and viola!
4. Update - This allows updating of existing pages - the title, name and all other fields.
5. Replace - This completely replaces all existing child pages with new pages. There are checks that prevent this method working if there are any child pages with their own children or other content fields that are not empty. This check can be disabled in the module config settings, but please be very careful with this.
6. Export to CSV - Generates a CSV file containing the fields for all child pages. Fields to be exported can be fixed or customized by the user. Also includes an API export method.

In Add, Update, and Replace modes you can enter CSV formatted rows to populate all text/numeric fields. This can be used to create new pages or to update existing pages. CSV field pairings can be defined to make it easy for editors to periodically create new pages, or update the fields in existing pages.

There is also an exportCsv() API method that can be used like this:
```
<?php
// export as CSV if csv_export=1 is in url
if($input->get->csv_export==1){
   $modules->get('ProcessChildrenCsvExport'); // load module
   // delimiter, enclosure, file extension, names in first row, multiple field separator, format values, pages to include (selector string), array of field names
   $page->exportCsv(',', '"', 'csv', true, "\r", true, 'include=all', array('title','body','images','textareas'));
   //$page->exportCsv() - this version uses the defaults from the module or page specific settings
}
// display content of template with link to same page with appended csv_export=1
else{
   include("./head.inc");

   echo "<a href='./?csv_export=1'>Export Child Pages as CSV</a>"; //link to initiate export

   include("./foot.inc");
}
```


### Access permission

This module requires a new permission: "batch-child-editor". This permission is created automatically on install and is added to the superuser role, but it is up to the developer to add the permission to other roles as required.


### Config Settings

There are module-wide config settings, but these can be overwritten with page specific permissions which allows for highly customized creation and editing tools.

* Which pages and templates will have the editor available and which can be separately configured.
* Which edit modes should be availble to the user.
* Alternate parent page - allows editing of external page tree.
* Which data entry options (Text, Upload, URL link) should be availble to the user.
* CSV import options.
* CSV field pairings - really powerful for creating and updating pages - read more about it in the config settings.
* CSV export options.
* Whether the name of the page should also be changed along with the title. This is a very important setting and should be considered carefully, especially is the child pages are URL accessible.
* Whether users can decide whether the name is also changed or not.
* Whether to disable content protection for existing child pages and their children.
* Trash or Delete.
* Load Batch interface Open or Collapsed (open for quicker access).
* Position interface (top, bottom, replace, new tab, inline fieldset).
* Custom Title, Description, and Notes for each mode - allows you to tailor the editing interface specifically to your content editors and to specific content.

#### Support forum:
https://processwire.com/talk/topic/6102-batch-child-editor/


## License

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

(See included LICENSE file for full license text.)






