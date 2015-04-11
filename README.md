## Joomla-updater
Welcome to the Eleven Joomla Updater project - a set of scripts we use to update our Git based Joomla projects.

## Requirements
We use these scripts on a Ubuntu Linux server with PHP 5.5 installed. Our source repositories are hosted in an Atlassian Stash installation. As Stash organizes repositories in projects, we decided to also clone them this way on our update server. That's why the update_joomla.sh script first descends into project directories and then into repository directories.

## Installation
Just publish these scripts in the root dir of your project/repository directories. 

## Usage
Run the update_joomla.sh script to start the update process. The script will scan the project and repository directories, pulls the latest changes from the origin master branch, and when a Joomla installation is found, it will try to update it to the latest version. Changes are pushed back to the Git origin master.

We use this script in conjunction with a script which clones all our Atlassian Stash repositories. And with a Jenkins CI server which is triggered by a commit, builds the project and deploys it to test and production environments.  

