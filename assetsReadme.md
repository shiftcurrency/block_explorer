The assets function was contributed by altsheets. Do keep the credits:
/views/templates/altsheetsCredits.html  included in the page
/views/templates/asset.phtml Thanks!

# HZ asset block explorer page
The [HZ blockexplorer](https://explorer.horizonplatform.io) ([orig source](https://github.com/pharesim/hz-blockexplorer)) was lacking a dedicated page for each asset, so I forked it into https://github.com/altsheets/hz-blockexplorer, and added that function - after [learning PHP](https://twitter.com/altsheets/status/615379388679483392) for it. But I am a total PHP newbie, so please check my code, then pull it upstream.

Main aspects:
* table with all properties from getAsset API call
* if issuer 'account' has an 'alias', show it
* 'quantity' derived from 'quantityQNT' and 'decimals'
* hyperlink to the transaction in which the asset was issued (for date, etc.)
* use of colors for fast grokking

Made sense so I built it:
* 'numberOfTransfers' and numberOfAccounts' already implemented, for future HZ version.
* if 'asset id' is unknown, hide most of the table, only show the error message. 
* specific assets can display freely configurable warnings (see below).

Explanations:
* on the right hand side, the most essential instructions for newbies are given
* warning about non-uniqueness of asset 'name'
* quick link to wallet (online / local)
* how to add the 'asset id' to the wallet
* links to external information: Videos, and long text "how to buy HZ, and how to buy a HZ asset - for newbies"

## examples

These are working examples of the new function, proudly presenting my asset "AAssetHZ":

* web page: proper asset http://188.226.155.38:8080/hzbe/?page=asset&id=8101260088962758269
* web page: wrong asset id http://188.226.155.38:8080/hzbe/?page=asset&id=0
* json api: proper asset http://188.226.155.38:8080/hzbe/api.php?page=asset&id=8101260088962758269

Great, isn't it?

## warnings
Some assets are known to be **test assets**, or **revoked**, or even **scams**. 

However, the (too) centralized power of the only official block explorer ... needs a balance between user protection and issuer protection (censorship is bad)!  

So *all assets are always still shown* - but in some cases with a clear warning. The warnings can be freely chosen, and are hardcoded into an XML file. 

I urge the team to always link back to a forum post where detailed explanations are given, and can be discussed - so that e.g. the issuer gets a chance to react to the suspicion. 

**examples:**
* test asset http://188.226.155.38:8080/hzbe/?page=asset&id=5903523947573024709 clearly intended to be only a test
* revoked asset  http://188.226.155.38:8080/hzbe/?page=asset&id=16661902544444460183 revoked by the issuer
* ~~perhaps scam http://188.226.155.38:8080/hzbe/?page=asset&id=3 there must be a heavy suspicion before using this!~~
* ~~probably scam http://188.226.155.38:8080/hzbe/?page=asset&id=4 almost sure, not many doubts left.~~ 
* proven scam http://188.226.155.38:8080/hzbe/?page=asset&id=5 definitely no doubts.

In case the team decides to drop all warnings completely, the easiest way is to keep all code intact, but just replace data/assetwarnings.xml by data/assetwarnings.xml.empty . In case the warnings are kept and extended, and that XML-file ever gets large ... it makes sense to load it only once and keep it in memory, instead of for each call (like now). See the comments in classes/model.php -> checkAssetWarnings(). 

**Discussion**
Please join our discussion in the [horizonplatform community forum](https://horizonplatform.io/topic/b-e-e-f-a-block-explorer-extension-for-assets-page-in-b-e-one-per-asset/) why or if to rather drop the "perhaps" and "probably" levels.

## affected files
In comparison to the [original block explorer](https://github.com/pharesim/hz-blockexplorer) I have changed or added at least these files [in my fork](https://github.com/altsheets/hz-blockexplorer): 

* assetsReadme.md (this readme here)
* todo.txt (some problems in the existing code that I noticed)
* prepareServer.sh (useful to install the block explorer on your own server!)
* classes
  * classes/controller.php
  * classes/model.php
* config/config.php
* views/templates
  * views/templates/asset.phtml
  * views/templates/altsheetsCredits.html
* views/api
  * views/api/templates/asset.phtml
  * views/api/templates/home.phtml
* views/cli/templates/asset.phtml (untested, I did not know how to)
* data/assetwarnings.xml (hardcoded warnings for specified assets)
* data/assetwarnings.xml.empty (a placeholder to switch off that function completely)

... perhaps even more. Check the commits on https://github.com/altsheets/hz-blockexplorer .

## my first big commit went strange - diff whitespace ignore
My main changes & additions to pharesim's original source code were commited in [7b15ade...](https://github.com/altsheets/hz-blockexplorer/commit/7b15ade59030c3c04cc60c8e4ce95e3d366c0aac), and the github 'diff' view pretends that ALL lines have been changed - which is wrong. I had changed only a tiny fraction. It came about when I transferred the files between Windows & Linux.

The github support now helped me with this ["github secret": ?w=1](https://github.com/blog/967-github-secrets) which solves it all: https://github.com/altsheets/hz-blockexplorer/commit/7b15ade59030c3c04cc60c8e4ce95e3d366c0aac?diff=split&w=1
Now you can see that e.g. **in model.php I only made changes after line 312.**

In the future I will sync machines only by push/pull through github, not by FTP anymore. I promise ;-) 
 
 
## license
Please pull upstream into the official block explorer - that's why I have built it.

But please (a) keep my credits in there: 

    This asset page function was contributed by AltSheetsDev. 
    Placed under my 'giveback license v04'. Click for how  
    to support me - or simply send some HZ to 
    NHZ-Q675-SGBG-LQ43-D38L6 now. Thanks!

and (b) consider to send me a big bounty for this work.

Thanks a lot.

    your happy AltSheetsDev on June 29th to July 2nd, 2015 
       

### temporary notes
When this is pulled upstream, replace all above 188.226.155.38:8080 with the official HZ blockexplorer domain. Then delete this note.
