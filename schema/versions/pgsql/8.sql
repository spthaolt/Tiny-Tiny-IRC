begin;

create table ttirc_shorturls(id serial not null primary key,
	url text not null unique,
	created timestamp not null default NOW());

update ttirc_version set schema_version = 8;

commit;
