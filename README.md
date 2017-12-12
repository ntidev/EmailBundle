# NTIEmailBundle

### Installation

1. Install the bundle using composer:

    ```
    $ composer require ntidev/email-bundle "dev-master"
    ```

2. Add the bundle configuration to the AppKernel

    ```
    public function registerBundles()
    {
        $bundles = array(
            ...
            new NTI\EmailBundle\NTIEmailBundle(),
            ...
        );
    }
    ```

3. Update the database schema

    ```
    $ php app/console doctrine:schema:update
    ```

### Cronjob

Schedule the following cronjob to check and send emails in the queue:

    ```
    * * * * * php app/console  /path/to/project/app/console nti:email:check
    ```