
license="""
##############################################################
##                                                          ##
##                              Remaining HZ distribution   ##
##                                Version v01 "indepence"   ## 
##                                     (4th of July 2015)   ##
##                                                          ##
##############################################################
## To be take with a grain of salt!                         ##
##                                                          ##
## Very unclear: future number of hallmarked nodes          ##
## Unknown: regular bounties for other-than-node-bounties   ##
##                                                          ##
## All this is open for discussion. Not truth, but maths.   ##
##############################################################
##                                                          ##
## Done by:   altsheets                                     ##
## License:   giveback license v04                          ##
##            http://altsheets.ddns.net/give/               ##
## Donate to: NHZ-Q675-SGBG-LQ43-D38L6 - thx                ## 
##                                                          ##
##############################################################
"""
 
## TODO: get this from https://explorer.horizonplatform.io/api.php?page=distributed
## 4/July/2015 13:15 UTC: 851306829.88887 HZ
undistributed = 1000000000 - 851306829.88887
dateToday = "4 July 2015"

## new rule was announced in 
## https://bitcointalk.org/index.php?topic=823785.msg11784831#msg11784831  
dailyRatePercent = 0.5


## choosable parameters:
 
## node bounty payment of the past 7 days were:
## 458.0852039, 495.5401388, 510.2040816, 571.1022273, 570.1254276, 591.7159763, 479.8464491
## --> average 525 HZ --> 10^6 / 525 = 1905  
numberOfPaidNodes = 1905
## please give suggestions how that number might change with payout.


# starting values, they get changed once per table, 
# table ends when the threshold is crossed:
stopAt = 100          # threshold: daily payout per node minimum HZ
HZdigits = 0          # digits for the last column (per node bounty)
printEveryXdays=15


## Not only hallmarked nodes get bounties.
## There are all kinds of other bounties, e.g. for the team;
## this is the starting value, later it might reduced?
 
otherBountiesPerDay = 0 
## TODO: set to 0 until (e.g. an average value for the past 3 months) has been said.


## constants
dateformat = "%d %B %Y"
line = "%18s: left = %9d HZ -> per day --> other: %d HZ; nodes: %d HZ = %d * %s HZ"
import datetime
oneDay = datetime.timedelta(days=1)


def extrapolateNodeBounties(stopAt, printEveryXdays, firstDay, 
                            ud, otherBountiesPerDay, HZdigits):
    "it would be nice to have a comment which explains this *lol*"
    
    day = datetime.datetime.strptime(firstDay, dateformat)

    dayCount = 0    # for printEveryXdays
    stopNow = False # flag for one more print if threshold crossed
    
    while (True):
        nodeBountiesThatDay = ud * dailyRatePercent / 100
        dTD_perNode = nodeBountiesThatDay / numberOfPaidNodes 
        
        if (dayCount % printEveryXdays==0 
            or stopNow):
            
            perNode = ("%." + "%d" % (HZdigits) + "f") % dTD_perNode
            print line % (datetime.datetime.strftime(day, dateformat), 
                          ud, otherBountiesPerDay, 
                          nodeBountiesThatDay, numberOfPaidNodes, perNode)
            
        ud = ud - nodeBountiesThatDay - otherBountiesPerDay
        day = day + oneDay
        dayCount += 1

        if stopNow: break
        if (dTD_perNode<stopAt): stopNow = True
         
    return datetime.datetime.strftime(day, dateformat), ud


def successionOfTables(firstDay = dateToday, ud = undistributed):
    # the 2 starting values get reduced in each iteration.
    # print several successive tables:

    global stopAt, printEveryXdays, otherBountiesPerDay, HZdigits
    
    firstDay, ud = extrapolateNodeBounties(stopAt, printEveryXdays, firstDay,
                                           ud, otherBountiesPerDay, HZdigits)
    print "daily node bounty threshold crossed. Remaining: %d HZ for %s" % (ud, firstDay)
    print
    
    printEveryXdays *= 2
    stopAt /= 10
    HZdigits += 1
    otherBountiesPerDay /= 5
    firstDay, ud = extrapolateNodeBounties(stopAt, printEveryXdays, firstDay,
                                           ud, otherBountiesPerDay, HZdigits)
    print "daily node bounty threshold crossed. Remaining: %d HZ for %s" % (ud, firstDay)
    print
    
    printEveryXdays *= 2
    stopAt /= 5
    HZdigits += 1
    otherBountiesPerDay /= 5
    firstDay, ud = extrapolateNodeBounties(stopAt, printEveryXdays, firstDay,
                                           ud, otherBountiesPerDay, HZdigits) 
    print "daily node bounty threshold crossed. Remaining: %d HZ for %s" % (ud, firstDay)


if __name__ == "__main__":
    print license
    successionOfTables()
    
    