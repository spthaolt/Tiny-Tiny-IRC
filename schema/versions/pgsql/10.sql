begin;

create index ttirc_messages_incoming on ttirc_messages(incoming);

update ttirc_version set schema_version = 10;

commit;
