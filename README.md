### Use these cron jobs:
``` 
# Swiftmailer cronjobs
* * * * * php /var/www/greenlink/glpartnerportal/app/console swiftmailer:spool:send --env=dev 
* * * * * sleep 29 ; php /var/www/greenlink/glpartnerportal/app/console swiftmailer:spool:send --env=dev

# NTIEmailBundle cronjobs
* * * * * php /var/www/greenlink/glpartnerportal/app/console nti:email:check
* * * * * sleep 29 ; php /var/www/greenlink/glpartnerportal/app/console nti:email:check
```
