--
-- PostgreSQL database dump
--

-- Dumped from database version 17.4 (Debian 17.4-1.pgdg120+2)
-- Dumped by pg_dump version 17.4 (Debian 17.4-1.pgdg120+2)

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
-- Name: citext; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS citext WITH SCHEMA public;


--
-- Name: EXTENSION citext; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION citext IS 'data type for case-insensitive character strings';


--
-- Name: pg_stat_statements; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_stat_statements WITH SCHEMA public;


--
-- Name: EXTENSION pg_stat_statements; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_stat_statements IS 'track planning and execution statistics of all SQL statements executed';


--
-- Name: update_game_slug(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_game_slug() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            DECLARE
                base_slug TEXT;
                new_slug TEXT;
                counter INTEGER;
            BEGIN
                IF NEW.is_visible = true AND (OLD.is_visible = false OR OLD.is_visible IS NULL) AND
                   (NEW.slug IS NULL OR NEW.slug = '') THEN
                    -- Create base slug from name
                    base_slug := LOWER(REGEXP_REPLACE(NEW.name, '[^a-zA-Z0-9]+', '-', 'g'));
                    -- Remove leading/trailing hyphens
                    base_slug := TRIM(BOTH '-' FROM base_slug);

                    -- Start with base slug
                    new_slug := base_slug;
                    counter := 1;

                    -- Keep trying with incrementing numbers until we find a unique slug
                    WHILE EXISTS (
                        SELECT 1 FROM games
                        WHERE slug = new_slug
                        AND id != NEW.id
                    ) LOOP
                        new_slug := base_slug || '-' || counter;
                        counter := counter + 1;
                    END LOOP;

                    NEW.slug := new_slug;
                END IF;
                RETURN NEW;
            END;
            $$;


--
-- Name: update_game_version_latest_flag(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.update_game_version_latest_flag() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            DECLARE
                current_latest_id bigint;
                new_latest_id bigint;
            BEGIN
                -- For INSERT or UPDATE
                IF (TG_OP = 'INSERT') OR (TG_OP = 'UPDATE' AND OLD.published_at <> NEW.published_at) THEN
                    -- Get current latest version for this game
                    SELECT id INTO current_latest_id
                    FROM game_versions
                    WHERE game_id = NEW.game_id AND is_latest = true;

                    -- Find what should be the latest version
                    SELECT id INTO new_latest_id
                    FROM game_versions
                    WHERE game_id = NEW.game_id
                    ORDER BY published_at DESC
                    LIMIT 1;

                    -- Only update if there's a change in which version is latest
                    IF COALESCE(current_latest_id, 0) <> new_latest_id THEN
                        -- Set is_latest=false for old latest
                        IF current_latest_id IS NOT NULL THEN
                            UPDATE game_versions
                            SET is_latest = false
                            WHERE id = current_latest_id;
                        END IF;

                        -- Set is_latest=true for new latest
                        UPDATE game_versions
                        SET is_latest = true
                        WHERE id = new_latest_id;
                    END IF;
                -- For DELETE
                ELSIF TG_OP = 'DELETE' THEN
                    -- Only proceed if we're deleting a latest version
                    IF OLD.is_latest THEN
                        -- Find new latest version
                        SELECT id INTO new_latest_id
                        FROM game_versions
                        WHERE game_id = OLD.game_id
                        ORDER BY published_at DESC
                        LIMIT 1;

                        -- Set new latest version if one exists
                        IF new_latest_id IS NOT NULL THEN
                            UPDATE game_versions
                            SET is_latest = true
                            WHERE id = new_latest_id;
                        END IF;
                    END IF;
                END IF;

                RETURN NULL;
            END;
            $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

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
-- Name: game_versions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.game_versions (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    published_at timestamp(0) without time zone NOT NULL,
    game_id bigint NOT NULL,
    version character varying(20) NOT NULL,
    devlog character varying(250),
    is_windows boolean DEFAULT false NOT NULL,
    is_linux boolean DEFAULT false NOT NULL,
    is_mac boolean DEFAULT false NOT NULL,
    is_android boolean DEFAULT false NOT NULL,
    is_web boolean DEFAULT false NOT NULL,
    rating double precision,
    rating_count integer,
    is_latest boolean DEFAULT false NOT NULL
);


--
-- Name: game_versions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.game_versions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: game_versions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.game_versions_id_seq OWNED BY public.game_versions.id;


--
-- Name: games; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.games (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    initially_published_at timestamp(0) without time zone,
    game_id integer NOT NULL,
    name public.citext NOT NULL,
    status character varying(50) DEFAULT 'In development'::character varying NOT NULL,
    is_visible boolean DEFAULT false NOT NULL,
    is_nsfw boolean DEFAULT false NOT NULL,
    description text,
    url character varying(255) NOT NULL,
    thumb_url character varying(255),
    tags character varying(255),
    game_engine character varying(50) DEFAULT 'unknown'::character varying NOT NULL,
    error text,
    authors public.citext,
    custom_tags public.citext DEFAULT ''::public.citext NOT NULL,
    uploads jsonb DEFAULT '{}'::jsonb,
    slug public.citext,
    is_feedless boolean DEFAULT false NOT NULL,
    source_language_id character varying(3)
);


--
-- Name: games_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.games_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: games_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.games_id_seq OWNED BY public.games.id;


--
-- Name: iso_639_3_languages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.iso_639_3_languages (
    id character varying(3) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    part2b character varying(3),
    part2t character varying(3),
    part1 character varying(2),
    scope character varying(1) NOT NULL,
    type character varying(1) NOT NULL,
    ref_name character varying(150) NOT NULL,
    comment character varying(150),
    flag_code character varying(2) NOT NULL
);


--
-- Name: language_mappings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.language_mappings (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_language_key character varying(50) NOT NULL,
    iso_code character varying(3) NOT NULL
);


--
-- Name: language_mappings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.language_mappings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: language_mappings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.language_mappings_id_seq OWNED BY public.language_mappings.id;


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
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email public.citext NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: processed_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.processed_events (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    event_id bigint NOT NULL,
    game_id bigint NOT NULL,
    processed_at timestamp(0) without time zone NOT NULL
);


--
-- Name: processed_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.processed_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: processed_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.processed_events_id_seq OWNED BY public.processed_events.id;


--
-- Name: raters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.raters (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    user_id integer NOT NULL,
    name public.citext NOT NULL,
    username public.citext
);


--
-- Name: raters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.raters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: raters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.raters_id_seq OWNED BY public.raters.id;


--
-- Name: ratings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ratings (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    published_at timestamp(0) without time zone NOT NULL,
    event_id bigint NOT NULL,
    game_id bigint NOT NULL,
    rater_id bigint NOT NULL,
    rating smallint NOT NULL,
    review text NOT NULL,
    is_visible boolean DEFAULT true NOT NULL,
    is_reviewed boolean DEFAULT false NOT NULL
);


--
-- Name: ratings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ratings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ratings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ratings_id_seq OWNED BY public.ratings.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email public.citext NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
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
-- Name: version_character_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_character_stats (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    iso_code character varying(10) NOT NULL,
    character_id character varying(50) NOT NULL,
    display_name character varying(100) NOT NULL,
    blocks integer DEFAULT 0 NOT NULL,
    words integer DEFAULT 0 NOT NULL
);


--
-- Name: version_character_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_character_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_character_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_character_stats_id_seq OWNED BY public.version_character_stats.id;


--
-- Name: version_language_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_language_stats (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    iso_code character varying(10) NOT NULL,
    blocks integer,
    words integer,
    menus integer,
    options integer
);


--
-- Name: version_language_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_language_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_language_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_language_stats_id_seq OWNED BY public.version_language_stats.id;


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: game_versions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions ALTER COLUMN id SET DEFAULT nextval('public.game_versions_id_seq'::regclass);


--
-- Name: games id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games ALTER COLUMN id SET DEFAULT nextval('public.games_id_seq'::regclass);


--
-- Name: language_mappings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings ALTER COLUMN id SET DEFAULT nextval('public.language_mappings_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: processed_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.processed_events ALTER COLUMN id SET DEFAULT nextval('public.processed_events_id_seq'::regclass);


--
-- Name: raters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters ALTER COLUMN id SET DEFAULT nextval('public.raters_id_seq'::regclass);


--
-- Name: ratings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings ALTER COLUMN id SET DEFAULT nextval('public.ratings_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: version_character_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats ALTER COLUMN id SET DEFAULT nextval('public.version_character_stats_id_seq'::regclass);


--
-- Name: version_language_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats ALTER COLUMN id SET DEFAULT nextval('public.version_language_stats_id_seq'::regclass);


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
-- Name: game_versions game_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions
    ADD CONSTRAINT game_versions_pkey PRIMARY KEY (id);


--
-- Name: games games_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_game_id_unique UNIQUE (game_id);


--
-- Name: games games_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_pkey PRIMARY KEY (id);


--
-- Name: games games_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_slug_unique UNIQUE (slug);


--
-- Name: iso_639_3_languages iso_639_3_languages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.iso_639_3_languages
    ADD CONSTRAINT iso_639_3_languages_pkey PRIMARY KEY (id);


--
-- Name: language_mappings language_mappings_game_language_key_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings
    ADD CONSTRAINT language_mappings_game_language_key_iso_code_unique UNIQUE (game_language_key, iso_code);


--
-- Name: language_mappings language_mappings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings
    ADD CONSTRAINT language_mappings_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: processed_events processed_events_event_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.processed_events
    ADD CONSTRAINT processed_events_event_id_unique UNIQUE (event_id);


--
-- Name: processed_events processed_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.processed_events
    ADD CONSTRAINT processed_events_pkey PRIMARY KEY (id);


--
-- Name: raters raters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters
    ADD CONSTRAINT raters_pkey PRIMARY KEY (id);


--
-- Name: raters raters_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters
    ADD CONSTRAINT raters_user_id_unique UNIQUE (user_id);


--
-- Name: ratings ratings_event_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_event_id_unique UNIQUE (event_id);


--
-- Name: ratings ratings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_pkey PRIMARY KEY (id);


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
-- Name: version_character_stats version_character_stats_game_version_id_iso_code_character_id_u; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT version_character_stats_game_version_id_iso_code_character_id_u UNIQUE (game_version_id, iso_code, character_id);


--
-- Name: version_character_stats version_character_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT version_character_stats_pkey PRIMARY KEY (id);


--
-- Name: version_language_stats version_language_stats_game_version_id_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats
    ADD CONSTRAINT version_language_stats_game_version_id_iso_code_unique UNIQUE (game_version_id, iso_code);


--
-- Name: version_language_stats version_language_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats
    ADD CONSTRAINT version_language_stats_pkey PRIMARY KEY (id);


--
-- Name: game_versions_game_id_is_latest_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX game_versions_game_id_is_latest_index ON public.game_versions USING btree (game_id, is_latest);


--
-- Name: games_authors_fulltext; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_authors_fulltext ON public.games USING gin (to_tsvector('english'::regconfig, (authors)::text));


--
-- Name: games_custom_tags_fulltext; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_custom_tags_fulltext ON public.games USING gin (to_tsvector('english'::regconfig, (custom_tags)::text));


--
-- Name: games_visible_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_visible_index ON public.games USING btree (is_visible);


--
-- Name: idx_game_versions_latest_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_game_versions_latest_lookup ON public.game_versions USING btree (is_latest, game_id, id);


--
-- Name: idx_version_stats_version_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_version_stats_version_lang ON public.version_language_stats USING btree (game_version_id, iso_code);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: processed_events_event_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX processed_events_event_id_index ON public.processed_events USING btree (event_id);


--
-- Name: processed_events_game_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX processed_events_game_id_index ON public.processed_events USING btree (game_id);


--
-- Name: processed_events_processed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX processed_events_processed_at_index ON public.processed_events USING btree (processed_at);


--
-- Name: raters_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX raters_name_index ON public.raters USING btree (name);


--
-- Name: ratings_game_id_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_game_id_visible_has_review_index ON public.ratings USING btree (game_id, is_visible, is_reviewed);


--
-- Name: ratings_published_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_published_at_index ON public.ratings USING btree (published_at);


--
-- Name: ratings_rater_id_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_rater_id_visible_has_review_index ON public.ratings USING btree (rater_id, is_visible, is_reviewed);


--
-- Name: ratings_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_visible_has_review_index ON public.ratings USING btree (is_visible, is_reviewed);


--
-- Name: games update_game_slug_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_game_slug_trigger BEFORE INSERT OR UPDATE ON public.games FOR EACH ROW EXECUTE FUNCTION public.update_game_slug();


--
-- Name: game_versions update_game_version_latest_flag_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_game_version_latest_flag_trigger AFTER INSERT OR DELETE OR UPDATE OF published_at ON public.game_versions FOR EACH ROW EXECUTE FUNCTION public.update_game_version_latest_flag();


--
-- Name: game_versions game_versions_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions
    ADD CONSTRAINT game_versions_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id);


--
-- Name: games games_source_language_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_source_language_id_foreign FOREIGN KEY (source_language_id) REFERENCES public.iso_639_3_languages(id) ON DELETE SET NULL;


--
-- Name: ratings ratings_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id);


--
-- Name: ratings ratings_rater_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_rater_id_foreign FOREIGN KEY (rater_id) REFERENCES public.raters(id);


--
-- Name: version_character_stats version_character_stats_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT version_character_stats_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_language_stats version_language_stats_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats
    ADD CONSTRAINT version_language_stats_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

--
-- PostgreSQL database dump
--

-- Dumped from database version 17.4 (Debian 17.4-1.pgdg120+2)
-- Dumped by pg_dump version 17.4 (Debian 17.4-1.pgdg120+2)

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
1	2014_10_12_000000_create_users_table	1
2	2014_10_12_100000_create_password_reset_tokens_table	1
3	2019_08_19_000000_create_failed_jobs_table	1
4	2019_12_14_000001_create_personal_access_tokens_table	1
5	2024_03_01_000636_create_games_table	1
6	2024_03_01_000706_create_raters_table	1
7	2024_03_01_000722_create_ratings_table	1
8	2024_03_01_004125_create_game_versions_table	1
9	2024_05_12_211909_modify_raters_table	1
10	2024_05_12_211954_create_game_rater_table	1
11	2024_05_13_200336_drop_game_rater_table	1
12	2024_05_13_200810_modify_games_table	1
13	2024_05_18_233635_modify_games_table	1
14	2024_07_25_193937_add_uploads_column_to_games_table	1
15	2024_12_28_001000_create_iso_639_3_languages_table	1
16	2024_12_28_001100_create_language_mappings_table	1
17	2024_12_28_001200_create_version_language_stats_table	1
18	2024_12_28_001300_create_version_character_stats_table	1
19	2024_12_28_114217_create_game_supported_languages_table	1
20	2024_12_28_114259_migrate_version_stats	1
21	2024_12_28_114327_modify_games_table	1
22	2024_12_28_150226_rename_boolean_columns	1
23	2024_12_28_233201_add_is_latest_to_game_versions_table	1
24	2024_12_28_234040_add_indices_to_version_tables	1
25	2025_01_05_174709_add_slug_to_games_table	1
26	2025_01_05_230049_drop_stats_columns_from_game_versions_table	1
27	2025_01_06_105417_create_processed_events_table	1
28	2025_01_06_130710_add_is_feedless_to_games_table	1
29	2025_01_06_134332_drop_duplicate_columns_from_games_table	1
30	2025_01_10_172021_add_slug_trigger_for_games_table	1
31	2025_01_10_220426_add_source_language_to_games_table	1
32	2025_01_10_221901_drop_game_supported_languages_table	1
33	2025_01_17_111449_set_default_values_in_database	1
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 33, true);


--
-- PostgreSQL database dump complete
--

