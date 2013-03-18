## BEFORE YOU START ##

The OpenShift crew run their own "official" quickstart which I recommend:

https://github.com/openshift-quickstart/tiny_tiny_rss-openshift-quickstart

## Running on OpenShift ##

Create an account at http://openshift.redhat.com/

Create a PHP application

	rhc app create reader -t php-5.3 -l $USERNAME

Add mysql support to your application
    
	rhc cartridge add -a reader -c mysql-5.1 -l $USERNAME

Add this upstream Piwik quickstart repo

	cd reader
	rm -rf *
	git remote add upstream -m master https://github.com/ghelleks/Tiny-Tiny-RSS-quickstart.git
	git pull -s recursive -X theirs upstream master

Now edit php/config.php to add your email addresses where appropriate. Don't
forget to commit the changes.

Then push the repo upstream to OpenShift

	git push

That's it, you can now checkout your application at:

	http://reader-$yourlogin.rhcloud.com

Your default username is "admin".
The default password is "password".

### Repo layout ###

* data/ - Where we put caches, feed icons, and lockfiles
* php/ - Externally exposed php code goes here
* libs/ - Additional libraries
* misc/ - For not-externally exposed php code
* deplist.txt - list of pears to install
* .openshift/action_hooks/deploy - Where we populate the database
* .openshift/action_hooks/post_deploy - Where we set up the update mechanism (probably b0rken right now)

