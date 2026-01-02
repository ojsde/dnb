# Export or deposition of articles

***The English version of this manual is under construction. Please switch to the German language version for full details.***

1. [State of an article](export#status)
2. [Deposition to the DNB Hotfolder](export#deposit)
3. [Export](export#export)
4. [Supplementary material](export#supplementary)

## <a name="status"></a>State of an article

An article can be in one of the following states:

***Not deposited***: The article has not been delivered to the DNB yet (it was not deposited to the DNB Hotfolder from within OJS and not marked registered).

***Deposited***: The article has been deposited to the DNB Hotfolder from within OJS.

***Marked registered***: The article was manually marked as registered. You may mark articles as registered (see button *"Mark Registered"*) to indicate that the article was delivered to the DNB outside of OJS, e.g. via the DNB web form.

## <a name="deposit"></a>Deposition to the DNB Hotfolder

The ***Deposit*** function generates a zipped archive as required by the specification of the DNB Hotfolder and directly submits it to the DNB Hotfolder via SFTP. For the function to work you have to provide your Hotfolder login credentials on the settings tab. Please also make sure that your server and your local IT infrastructure (e.g. firewalls) support outgoing SFTP connections. 

## <a name="export"></a>Export

The ***Export*** function generates a zipped archive as required by the specification of the DNB Hotfolder for download to your local machine. The exported archives are formated as required by the DNB and are ready for manual upload to the DNB Hotfolder.

## <a name="supplementary"></a>Supplementary material

OJS does currently not support a means to assign supplementary material to specific document galleys. If more than one document galley exists an assignment of supplementary galleys to their corresponding document galleys is ambiguous. In these cases articles are marked with a red exclamation triangle.

The DNB Export Plugin always exports all supplementary material with each individual document galley. Instead of directly uploading articles were an assigement is not unambiguously possible, please consider exporting those articles, remove incoreectly assigned supplementary material from the archive and upload the archives manually to the DNB Hotfolder. 