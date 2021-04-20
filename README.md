Time Spent Learning Plugin
==============================

This plugin calculates time that some user spends on some course and activities within the course.

Installation
------------

The plugin is installed as any other local plugin. Put source to local/lambda_dedication folder,
and Totara will do the rest.

After installation, it is possible to import historical data to plugin's tables.
As admin user, navigate to Site Administration -> Plugins -> Local plugins -> Time Spent Learning.
At that page click on Start Import button. Import page has progress bar, and can take a few hours to finish.

Import process can be executed only once, and it cannot be restarted once it is started. Therefore, do not
close the browser while it is working. Restarting the import process would probably include truncating
all five tables, but I'm not sure how would it work while some users are browsing the site at the same time.
That's why this is not implemented.

How it works
------------

Plugin uses 5 tables: local_ld_lastactivity, local_ld_course_day, local_ld_module, and local_ld_module_day. Table descriptions are in db/install.xml file.

First thing that plugin does upon installation is to write the installation time to config_plugins table (db/install.php file).
This time is used in import process.

Plugin is subscribed to all events (see db/events.php file). Code that handles events is in classes/observer.php.

When a new event occurs, plugin does following:

1. Read a row from local_ld_lastactivity table for the user from the event.
2. Calculate time that elapsed since the previous event, i.e. dedication time = event->time - lastactivity->time.
3. Immediately update row in local_ld_lastactivity table with time and courseid from the event.
4. Dedication time (from step 2.) is used to update total dedication time for course (table local_ld_course) that was found in lastactivity row
   (not the one from the event - it will be updated when the next event for that user arrives).  
   At the same time the course dedication at day level is updated (table local_ld_course_day).
5. If the event is related to a module within a course (i.e. $event->contextlevel == CONTEXT_MODULE)
   than local_ld_module and local_ld_module_day tables are also updated, similarly to how the course dedication tables are updated.

The plugin does not track time spent on the course with id = 1 (which is the site itself).

Code for import process is in import_logs.php and locallib.php files. Import does following:

1. Set the plugin's importstatus to 'inprogress' (this is written to config_plugins table).
2. Get ids of all users that are not deleted. It also skips guest and admin users (with ids 1 and 2).
3. For each user:
   1. Get data from both log and logstore_standard_log tables, that happened before the installation time, ordered by timestamp
   2. Calculate dedications for courses and modules (similar to event handler). 
      Dedications are kept in memory until all logs for that user are read.
   3. Write dedications for courses and modules to database.
4. Set the plugin's importstatus to 'finished'.

TODO
----

- Do not calculate time for users that are logged in as someone else. This might help: \core\session\manager::is_loggedinas()
