<?xml version="1.0" encoding="utf-8"?>
<livelookup version="1.0" columns="first_name,last_name">
	@foreach ($results as $result)
        <customer>
            <!-- These are standard elements which can be inserted back into a request -->
            <customer_id>{{ $result->getJobTitle() }}</customer_id>
            <first_name>{{ $result->getGivenName() }}</first_name>
            <last_name>{{ $result->getSurName() }}</last_name>
            <email>{{ $result->getMail() }}</email>
            <phone>{{ $result->getMobilePhone() }}</phone>
            <!-- These are custom elements which may be useful to the help desk staff member -->
            @if($result->getManager())
            <Manager>{{ $result->getManager()->getProperties()['displayName'] }}</Manager>
            @endif
        </customer>
    @endforeach
</livelookup>