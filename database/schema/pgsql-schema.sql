--
-- PostgreSQL database dump
--

\restrict 1AhszbLnpOs1mrWdiDxfmOCOOFVohSyUQiacJispLUAqyjUQwBSy79sZNcHikRD

-- Dumped from database version 16.12
-- Dumped by pg_dump version 17.8

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.attachments (
    id bigint NOT NULL,
    filename character varying(255) NOT NULL,
    original_filename character varying(255) NOT NULL,
    file_path character varying(255) NOT NULL,
    mime_type character varying(255) NOT NULL,
    file_size integer NOT NULL,
    attachable_type character varying(255) NOT NULL,
    attachable_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.attachments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.attachments_id_seq OWNED BY public.attachments.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description character varying(255),
    color character varying(7) DEFAULT '#3B82F6'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: ticket_replies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_replies (
    id bigint NOT NULL,
    ticket_id bigint NOT NULL,
    user_id bigint NOT NULL,
    message text NOT NULL,
    is_internal boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    reply_to_id bigint,
    edited_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: ticket_replies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_replies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_replies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_replies_id_seq OWNED BY public.ticket_replies.id;


--
-- Name: ticket_user_states; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_user_states (
    id bigint NOT NULL,
    ticket_id bigint NOT NULL,
    user_id bigint NOT NULL,
    last_seen_at timestamp(0) without time zone,
    dismissed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ticket_user_states_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_user_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_user_states_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_user_states_id_seq OWNED BY public.ticket_user_states.id;


--
-- Name: tickets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tickets (
    id bigint NOT NULL,
    ticket_number character varying(255) NOT NULL,
    subject character varying(255) NOT NULL,
    description text NOT NULL,
    priority character varying(255) DEFAULT 'medium'::character varying NOT NULL,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    user_id bigint NOT NULL,
    assigned_to bigint,
    category_id bigint NOT NULL,
    due_date timestamp(0) without time zone,
    resolved_at timestamp(0) without time zone,
    closed_at timestamp(0) without time zone,
    satisfaction_rating integer,
    satisfaction_comment text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255),
    contact_number character varying(30),
    email character varying(255),
    province character varying(120),
    municipality character varying(120),
    assigned_at timestamp(0) without time zone,
    super_users_notified_new_at timestamp(0) without time zone,
    technical_user_notified_assignment_at timestamp(0) without time zone,
    super_users_notified_unchecked_at timestamp(0) without time zone,
    super_users_notified_unassigned_sla_at timestamp(0) without time zone,
    technical_user_notified_sla_at timestamp(0) without time zone,
    CONSTRAINT tickets_priority_check CHECK (((priority)::text = ANY ((ARRAY['low'::character varying, 'medium'::character varying, 'high'::character varying, 'urgent'::character varying])::text[]))),
    CONSTRAINT tickets_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'in_progress'::character varying, 'pending'::character varying, 'resolved'::character varying, 'closed'::character varying])::text[])))
);


--
-- Name: tickets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tickets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tickets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tickets_id_seq OWNED BY public.tickets.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(255),
    department character varying(255),
    role character varying(255) DEFAULT 'client'::character varying NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT users_role_check CHECK (((role)::text = ANY (ARRAY['client'::text, 'shadow'::text, 'admin'::text, 'super_user'::text, 'technical'::text])))
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: attachments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attachments ALTER COLUMN id SET DEFAULT nextval('public.attachments_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: ticket_replies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies ALTER COLUMN id SET DEFAULT nextval('public.ticket_replies_id_seq'::regclass);


--
-- Name: ticket_user_states id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states ALTER COLUMN id SET DEFAULT nextval('public.ticket_user_states_id_seq'::regclass);


--
-- Name: tickets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets ALTER COLUMN id SET DEFAULT nextval('public.tickets_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: attachments attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attachments
    ADD CONSTRAINT attachments_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: ticket_replies ticket_replies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies
    ADD CONSTRAINT ticket_replies_pkey PRIMARY KEY (id);


--
-- Name: ticket_user_states ticket_user_states_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states
    ADD CONSTRAINT ticket_user_states_pkey PRIMARY KEY (id);


--
-- Name: ticket_user_states ticket_user_states_ticket_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states
    ADD CONSTRAINT ticket_user_states_ticket_id_user_id_unique UNIQUE (ticket_id, user_id);


--
-- Name: tickets tickets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_pkey PRIMARY KEY (id);


--
-- Name: tickets tickets_ticket_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_ticket_number_unique UNIQUE (ticket_number);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: attachments_attachable_type_attachable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attachments_attachable_type_attachable_id_index ON public.attachments USING btree (attachable_type, attachable_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: ticket_user_states_user_id_dismissed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_user_states_user_id_dismissed_at_index ON public.ticket_user_states USING btree (user_id, dismissed_at);


--
-- Name: ticket_user_states_user_id_last_seen_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_user_states_user_id_last_seen_at_index ON public.ticket_user_states USING btree (user_id, last_seen_at);


--
-- Name: tickets_assigned_at_technical_user_notified_sla_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_assigned_at_technical_user_notified_sla_at_index ON public.tickets USING btree (assigned_at, technical_user_notified_sla_at);


--
-- Name: tickets_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_created_at_index ON public.tickets USING btree (created_at);


--
-- Name: tickets_created_at_super_users_notified_unchecked_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_created_at_super_users_notified_unchecked_at_index ON public.tickets USING btree (created_at, super_users_notified_unchecked_at);


--
-- Name: tickets_priority_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_priority_index ON public.tickets USING btree (priority);


--
-- Name: tickets_status_assigned_to_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_assigned_to_index ON public.tickets USING btree (status, assigned_to);


--
-- Name: tickets_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_index ON public.tickets USING btree (status);


--
-- Name: tickets_status_priority_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_priority_created_at_index ON public.tickets USING btree (status, priority, created_at);


--
-- Name: ticket_replies ticket_replies_reply_to_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies
    ADD CONSTRAINT ticket_replies_reply_to_id_foreign FOREIGN KEY (reply_to_id) REFERENCES public.ticket_replies(id) ON DELETE SET NULL;


--
-- Name: ticket_replies ticket_replies_ticket_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies
    ADD CONSTRAINT ticket_replies_ticket_id_foreign FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_replies ticket_replies_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_replies
    ADD CONSTRAINT ticket_replies_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: ticket_user_states ticket_user_states_ticket_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states
    ADD CONSTRAINT ticket_user_states_ticket_id_foreign FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_user_states ticket_user_states_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_user_states
    ADD CONSTRAINT ticket_user_states_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: tickets tickets_assigned_to_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_assigned_to_foreign FOREIGN KEY (assigned_to) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: tickets tickets_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict 1AhszbLnpOs1mrWdiDxfmOCOOFVohSyUQiacJispLUAqyjUQwBSy79sZNcHikRD

--
-- PostgreSQL database dump
--

\restrict BMuW1As8qSh70QtUMmQ3382Or5tCCkxpgtrRg7cWAyVufTeQZm8ZAn58H951SsH

-- Dumped from database version 16.12
-- Dumped by pg_dump version 17.8

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2024_01_01_000001_create_users_table	1
2	2024_01_01_000002_create_categories_table	1
3	2024_01_01_000003_create_tickets_table	1
4	2024_01_01_000004_create_ticket_replies_table	1
5	2024_01_01_000005_create_attachments_table	1
6	2024_01_01_000006_create_sessions_table	1
7	2024_01_01_000007_create_cache_table	1
10	2026_02_18_000001_add_name_and_contact_number_to_tickets_table	1
11	2026_02_18_000002_add_hardware_and_software_categories	1
12	2026_02_18_000003_optimize_tickets_and_cleanup_replies	1
13	2026_02_19_000004_add_chat_state_fields_to_ticket_replies	1
14	2026_02_19_000005_drop_legacy_attachments_column_from_ticket_replies	1
16	2026_02_20_000007_add_email_province_municipality_to_tickets_table	1
19	2026_02_24_000010_backfill_legacy_ticket_profile_fields	1
20	2026_02_25_000011_create_ticket_user_states_table	1
21	2026_02_25_000012_add_ticket_email_alert_columns	1
22	2026_02_25_053350_create_failed_jobs_table	1
23	2026_02_25_053350_create_job_batches_table	1
24	2026_02_25_053350_create_jobs_table	1
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 26, true);


--
-- PostgreSQL database dump complete
--

\unrestrict BMuW1As8qSh70QtUMmQ3382Or5tCCkxpgtrRg7cWAyVufTeQZm8ZAn58H951SsH

