# AI-Bolit

The AI-Bolit scanner is probably the most effective tool for webmasters and site administrators to search for viruses and malicious code

## How to operate the AI-BOLIT scanner from the command line

> ⚠ Order site treatment and protection against hacking from [experts](https://palpalych.ru/).

The greatest functionality is available when running AI-BOLIT scanner in command line mode. This can be done either locally on a Windows/Unix/Mac OS X computer or directly on the hosting, if you have SSH access and the hosting does not limit the CPU resources consumed.

Show help

`php ai-bolit.php --help`

Run the scanner in "paranoid" mode (recommended to get the most detailed report)

`php ai-bolit.php --mode=2`

Run the scanner in "Expert" mode (recommended for infection assessment)

`php ai-bolit.php --mode=1`

Check one file "pms.db" for malicious code

`php ai-bolit.php -jpms.db`

Start the scanner with 512Mb memory installed

`php ai-bolit.php --memory=512M`

Установить максимальный размер проверяемого файла 900Kb

`php ai-bolit.php --size=900K`

Pause 500ms between files during scanning (to reduce load)

`php ai-bolit.php --delay=500`

Upload .aknown files from wordpress when scanning (the known_files directory should be in the same directory as ai-boilit.php)

`php ai-bolit.php --cms=wordpress`

Email the scan report to myreport@mail.ru

`php ai-bolit.php --report=myreport@mail.ru`

Create a report in the file /home/scanned/report_site1.html

`php ai-bolit.php --report=/home/scanned/report_site1.html`

Scan the /home/s/site1/public_html/ directory (the report will be created in this directory by default if the --report=report_file option is not set)

`php ai-bolit.php --path=/home/s/site1/public_html/`

Get the report in plain-text with the name site1.txt

`php ai-bolit.php -lsite1.txt`

You can combine calls, for example,

`php ai-bolit.php --size=300K --path=/home/s/site1/public_html/ --mode=2 --cms=wordpress`

By combining the AI-BOLIT scanner call with other unix commands, you can perform, for example, a batch scan of sites. Here is an example of checking several sites within an account. For example, if the sites are located inside the /var/www/user1/data/www directory, the command to run the scanner would be

`find /var/www/user1/data/www -maxdepth 1 -type d -exec php ai-bolit.php --path={} --mode=2 \;`

By adding the --report parameter you can control the directory where the scan reports will be generated.
 