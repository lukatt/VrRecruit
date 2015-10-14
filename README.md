Vreasy Task's confirmation
==========================

Introduction
------------

Welcome fellow nerd! We have created this project hoping that, with it, we will get a feeling of how you work and you could get a feeling of how would be to work at Vreasy. We believe that everybody should get a chance to prove herself/himself.

Here you'll find a somehow current subset of our core back-end classes and tools for building our front-end. We expect you to do an overall review of the structure and to be able to deduce (using trial an error or using your past experience working with MVC frameworks) where the different pieces of source-code should be placed. In the end, what we evaluate here, is how you get around the edges of the framework, how you can structure your code and how you reason about the different needed pieces to complete the assignment.

You can find the briefing for this task in **[The Vreasy Developer’s Quiz document](https://docs.google.com/document/d/19kYCiaYKmg6AqUn2ckrFZd9VGLudOrmlcXpIZ2Vz8yQ/edit?usp=sharing)**

If anything is unclear, please do not hesitate to contact us with questions! (<mailto:dev@vreasy.com>)

> PD: If you have no experience with PHP, we do use other technologies, but you will have to learn PHP since that's our core tech now days. You are welcome to submit the "test" in another public github repo, with the assignment done in what ever technology stack you feel comfortable with, instead of PHP.

How's to work at Vreasy?
-----------------------

At Vreasy we work with several technologies. At the back-end, we currently use PHP ([ZF1](https://github.com/zendframework/zf1)), NodeJs and even Java. On the front-end we deal with coffeescript, regular js and we use frameworks like [AngularJS](http://angularjs.org/) and a bit of [jQuery](http://jquery.com/).
We are working into moving our software into a SOA approach. So we build thin json APIs and we run most of what the user sees in the client side.

We use Unix-like OS, Github, Circleci, Jira, AWS Opsworks and Vagrant, and we continuously try to automate our operations.

We think that new technologies are good, but proven technologies are better.

Our team is a very diverse team, with people from all around the world and very diverse set of cultures.


Getting started
---------------

The task-confirmation assignment has to be setup on 2 environments: in your **workstation**, for you to be able to run the test suite, and in a **virtual machine** (VM from now on) where the web-server will run.
The VM box setups a [vagrant synced folder](https://docs.vagrantup.com/v2/synced-folders/) that [links the web-server to the folder](deploy/before_restart.rb#L24-L32) where your are working on your workstation. So changes you make in the source-code will be instantly available to the VM.

1. Setup the requirements:
    1. php >= 5.5.x
    1. [MySQL](https://dev.mysql.com/downloads/windows/installer/) >= 5.5.x
    1. node >= 0.10.33
    1. npm >= 3.3.x
    1. [composer Dependency Manager for PHP](http://getcomposer.org/doc/00-intro.md)
    1. [vagrant](https://docs.vagrantup.com/v2/installation/) >= 1.7.4
    1. [virtualbox](https://www.virtualbox.org/wiki/Downloads) >= 5.x
2. Fork the project and get the source code
    2. Click on the top-right **Fork** button and fork this repository
    2. Clone the `task-confirmation` branch of the project into your workstation

        ```shell
        git clone -b task-confirmation https://github.com/<your-username>/VrRecruit.git
        ```
3. Install PHP projects dependencies using composer (be patience, it could take a while)

    ```shell
    # Give composer enough time to download all of the packages
    export COMPOSER_PROCESS_TIMEOUT=6000

    composer install --no-interaction --prefer-dist
    ```
4. Install the front-end tools and dependencies using npm and bower

    ```shell
    # First npm so you get the tool's binaries
    npm install
    # After that, just run bower
    node_modules/bower/bin/bower install
    ```
5. Create the databases and setup the db users permissions (See the [db.php](https://github.com/Vreasy/VrRecruit/blob/task-confirmation/vreasy/application/configs/db.php)).

    ```sql
    # On your MySQL client execute the following
    create database vreasy_task_confirmation;
    create user 'vreasy'@'localhost' identified by '<put-db.php-password-here>';
    GRANT ALL PRIVILEGES on vreasy_task_confirmation.* to 'vreasy'@'localhost' WITH GRANT OPTION;

    create database vreasy_task_confirmation_test;
    create user 'ubuntu'@'localhost';
    GRANT ALL PRIVILEGES on vreasy_task_confirmation_test.* to 'ubuntu'@'localhost' WITH GRANT OPTION;
    ```
7. Check the test suite is working for you:

    ```shell
    php vendor/codeception/codeception/codecept build
    php vendor/codeception/codeception/codecept run --debug
    ```
    It should output something like ```OK (2 tests, 4 assertions)```

    [![Circle CI](https://circleci.com/gh/Vreasy/VrRecruit.png?style=badge)](https://circleci.com/gh/Vreasy/VrRecruit)
8. Get the VM Box so you get the web-application up and running

    ```shell
    # This will download the box (be patience, it could take a while)
    vagrant up --provider virtualbox
    # Once it was downloaded and setup, you need to provision it (be patience, it could take a while)
    vagrant reload --provision
    ```
    8. The VM opens the port 8011 for the website to work.

9. Update your workstation's hosts file

    ```
    127.0.0.1   vreasy.dev www.vreasy.dev
    ```
10. Test that everything is working fine
    10. Open your browser and navigate to `http://www.vreasy.dev:8011/` you should see an empty vreasy frame
    10. Also navigate to `http://www.vreasy.dev:8011/api/tasks` you should see a json collection of "tasks".
11. [Submit your Pull Request](https://help.github.com/articles/using-pull-requests/)! Upon submission, an automatic build will be triggered in [circleci](https://www.circleci.com). You might have to create an account there too, to trigger these builds on your fork of the project.

Help
----

Do you have any questions? Ask our developers (<mailto:dev@vreasy.com>)

Conventions
-----------

We follow [PSR-1](http://www.php-fig.org/psr/1/) and [PSR-2](http://www.php-fig.org/psr/2) from [PHP-FIG](http://www.php-fig.org/) and we do follow [Rest-full principles](http://en.wikipedia.org/wiki/Representational_state_transfer)
