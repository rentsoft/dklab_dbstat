dklab_dbstat: online daily statistics over your project's database (OLAP-like)
(C) Dmitry Koterov, http://en.dklab.ru/lib/dklab_dbstat/
License: GPL

ABSTRACT
--------

Dklab dbstat is a web tool which allows you to collect any statistical
information about your project and manipulate it in real-time. Below are 
samples of such statistic for e.g. a simple forum-based project:

  - number of accounts registered at your project per day, week, month, 
    quarter or total;
  - number of activated accounts (and percental comparison of this number
    to the total count of accounts);
  - number of topics created per day, week, ...;
  - number of posts created per ...;
  - average number of posts per topic per day, ...;
  - ...and other information which you could need to know about your forum.

This may look like the following:
  
                   | TOT   | AVG | Sun  Sat  Fri  Thu  Wed  Tue  Mon  Sun 
-------------------+-------+-----+---------------------------------------
Accounts/#         | 41401 | 698 | 741  736  724  751  708  411  389  891
Accounts/Activated | 32341 | 560 | 722  211  610  730  641  402  360  722
Topics created     | 1351  | 62  | 78   89   73   78   89   67   45   89
Posts created      | 78722 | 591 | 610  730  641  402  360  722  211  610
.........................................................................
    
You may add such statistical items to the system, remove them, organize 
into groups, mark with tags, preview, recalculate on demand (e.g. to fix
bugs in queries), edit etc. in 1-2 clicks using simple SQL queries. This 
is a kind of very simple OLAP system.


THE MAIN DBSTAT ADVANTAGE - ITS SIMPLICITY
------------------------------------------

Dbstat is so simple that in 1-2 monthes of usage you will find yourself
among tens (or even thousands) statistical items which you monitor day by
day (dbstat sends you an email with daily statistics). Your system begins
to talk to you daily, and you see its heartbeats!


STATISTICS IS SPLIT INTO ITEMS
------------------------------

You may see above that statistical items are quite simple and may be 
represented mostly as plain and atomic SQL queries with day/month/quarter
limitation. These SQL queries are typically written as:

SELECT COUNT(*)
FROM ...
JOIN ...
WHERE ...
  AND timestamp BETWEEN $FROM AND $TO

where $FROM and $TO are placeholders for period start/end timestamps.
These SQL queries of each item are executed regularry (typically daily),
and returned values are collected and organized into simple viewable
tables:

E.g. if we need to have daily counters, we assume $FROM = '2011-09-20 00:00:00' 
and $TO = '2011-09-20 23:59:59'. If we need weekly counters, we use
a weekly period: $FROM = '2011-09-20 00:00:00', $TO = '2011-09-26 23:59:59'.
You should understand that it it is not sometimes possible to calculate
weekly counters by summarizing of daily counters, so dbstat uses separated
calculations for each period.


