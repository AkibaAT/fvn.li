--
-- PostgreSQL database dump
--

\restrict hfHDefaRD2eMUMSUDLub5NUYmzUUykJLOttZykwY8JMhPHeZlwUTyseexhp14xI

-- Dumped from database version 18.4 (Debian 18.4-1.pgdg13+1)
-- Dumped by pg_dump version 18.4 (Debian 18.4-1.pgdg13+1)

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
-- Name: generate_unique_slug(text, bigint); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.generate_unique_slug(p_name text, p_id bigint) RETURNS text
    LANGUAGE plpgsql
    AS $$
            DECLARE
                base_slug TEXT;
                new_slug TEXT;
                counter INTEGER;
            BEGIN
                -- Create base slug from name
                base_slug := LOWER(REGEXP_REPLACE(p_name, '[^a-zA-Z0-9]+', '-', 'g'));
                -- Remove leading/trailing hyphens
                base_slug := TRIM(BOTH '-' FROM base_slug);

                -- Use a stable fallback when the name has no ASCII slug characters
                IF base_slug IS NULL OR base_slug = '' THEN
                    base_slug := 'game-' || p_id::TEXT;
                END IF;

                -- Start with base slug
                new_slug := base_slug;
                counter := 1;

                -- Keep trying with incrementing numbers until we find a unique slug
                WHILE EXISTS (
                    SELECT 1 FROM games
                    WHERE slug = new_slug
                    AND id != p_id
                ) LOOP
                    new_slug := base_slug || '-' || counter;
                    counter := counter + 1;
                END LOOP;

                RETURN new_slug;
            END;
            $$;


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
-- Name: addition_request_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.addition_request_users (
    id bigint NOT NULL,
    addition_request_id bigint NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: addition_request_users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.addition_request_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: addition_request_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.addition_request_users_id_seq OWNED BY public.addition_request_users.id;


--
-- Name: addition_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.addition_requests (
    id bigint NOT NULL,
    game_url character varying(255) CONSTRAINT addition_requests_itch_url_not_null NOT NULL,
    normalized_url character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    rejection_reason text,
    game_id bigint,
    reviewed_at timestamp(0) without time zone,
    reviewed_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    platform character varying(255),
    discord_notified_at timestamp(0) without time zone,
    CONSTRAINT addition_requests_platform_check CHECK (((platform)::text = ANY (ARRAY[('itch_io'::character varying)::text, ('steam'::character varying)::text, ('other'::character varying)::text]))),
    CONSTRAINT addition_requests_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('approved'::character varying)::text, ('rejected'::character varying)::text])))
);


--
-- Name: COLUMN addition_requests.platform; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.addition_requests.platform IS 'Platform where the game is hosted';


--
-- Name: addition_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.addition_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: addition_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.addition_requests_id_seq OWNED BY public.addition_requests.id;


--
-- Name: android_builds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.android_builds (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    build_id uuid NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    build_path character varying(255),
    keystore_path character varying(255),
    error_message text,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT android_builds_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('processing'::character varying)::text, ('completed'::character varying)::text, ('failed'::character varying)::text])))
);


--
-- Name: android_builds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.android_builds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: android_builds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.android_builds_id_seq OWNED BY public.android_builds.id;


--
-- Name: bug_report_comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bug_report_comments (
    id bigint NOT NULL,
    bug_report_id bigint NOT NULL,
    user_id bigint NOT NULL,
    message text NOT NULL,
    is_from_admin boolean DEFAULT false NOT NULL,
    is_read boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: bug_report_comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bug_report_comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bug_report_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bug_report_comments_id_seq OWNED BY public.bug_report_comments.id;


--
-- Name: bug_reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bug_reports (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    page_url character varying(2048) NOT NULL,
    page_title character varying(255),
    description text NOT NULL,
    request_parameters json,
    user_agent character varying(1024),
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    admin_notes text,
    resolved_by bigint,
    resolved_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_closed boolean DEFAULT false NOT NULL
);


--
-- Name: bug_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bug_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bug_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bug_reports_id_seq OWNED BY public.bug_reports.id;


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
-- Name: change_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs (
    id bigint NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
)
PARTITION BY RANGE ("timestamp");


--
-- Name: change_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.change_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: change_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.change_logs_id_seq OWNED BY public.change_logs.id;


--
-- Name: change_logs_y2025m01; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m01 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m02; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m02 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m03; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m03 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m04; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m04 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m05; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m05 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m06; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m06 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m07; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m07 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m08; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m08 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m09; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m09 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m10; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m10 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m11; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m11 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2025m12; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2025m12 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id bigint NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: change_logs_y2026m01; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m01 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m02; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m02 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m03; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m03 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m04; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m04 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m05; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m05 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m06; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m06 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m07; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m07 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m08; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m08 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m09; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m09 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m10; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m10 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m11; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m11 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2026m12; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2026m12 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m01; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m01 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m02; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m02 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m03; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m03 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m04; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m04 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m05; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m05 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m06; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m06 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m07; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m07 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m08; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m08 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m09; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m09 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m10; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m10 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m11; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m11 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: change_logs_y2027m12; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.change_logs_y2027m12 (
    id bigint DEFAULT nextval('public.change_logs_id_seq'::regclass) CONSTRAINT change_logs_id_not_null NOT NULL,
    "timestamp" timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_timestamp_not_null NOT NULL,
    event_type character varying(50) CONSTRAINT change_logs_event_type_not_null NOT NULL,
    entity_type character varying(50) CONSTRAINT change_logs_entity_type_not_null NOT NULL,
    entity_id bigint CONSTRAINT change_logs_entity_id_not_null NOT NULL,
    user_id bigint,
    changes jsonb,
    old_values jsonb,
    new_values jsonb,
    context jsonb,
    source character varying(20) DEFAULT 'web'::character varying,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_created_at_not_null NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP CONSTRAINT change_logs_updated_at_not_null NOT NULL
);


--
-- Name: version_character_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_character_stats (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    character_id bigint NOT NULL,
    iso_code character varying(10) NOT NULL,
    blocks integer DEFAULT 0 NOT NULL,
    words integer DEFAULT 0 NOT NULL
);


--
-- Name: character_version_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.character_version_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: character_version_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.character_version_stats_id_seq OWNED BY public.version_character_stats.id;


--
-- Name: characters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.characters (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_id bigint NOT NULL,
    character_id character varying(50) NOT NULL,
    display_names jsonb NOT NULL,
    first_seen_in_version_id bigint,
    last_seen_in_version_id bigint,
    display_name_corrections jsonb,
    gender character varying(255),
    species character varying(255),
    age character varying(255)
);


--
-- Name: characters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.characters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: characters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.characters_id_seq OWNED BY public.characters.id;


--
-- Name: click_stats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.click_stats (
    id bigint NOT NULL,
    game_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    link_id character varying(255),
    session_id character varying(255) NOT NULL,
    ip_address character varying(255),
    user_agent text,
    referrer text,
    clicked_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    user_id bigint,
    bot_reason character varying(32)
);


--
-- Name: click_stats_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.click_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: click_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.click_stats_id_seq OWNED BY public.click_stats.id;


--
-- Name: discord_channel_announcements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_channel_announcements (
    id bigint NOT NULL,
    game_id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    batch_key character varying(255),
    attempts integer DEFAULT 0 NOT NULL,
    error text,
    processed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: discord_channel_announcements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discord_channel_announcements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discord_channel_announcements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discord_channel_announcements_id_seq OWNED BY public.discord_channel_announcements.id;


--
-- Name: discord_notification_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_notification_history (
    id bigint NOT NULL,
    discord_server_id bigint NOT NULL,
    game_id bigint,
    notification_type character varying(255) DEFAULT 'update'::character varying NOT NULL,
    message_id character varying(255),
    channel_id character varying(255) NOT NULL,
    sent_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    delivery_status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    payload jsonb,
    batch_key character varying(255),
    delivery_mode character varying(20) DEFAULT 'send'::character varying NOT NULL,
    payload_hash character varying(64),
    CONSTRAINT discord_notification_history_delivery_status_check CHECK (((delivery_status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'sent'::character varying, 'failed'::character varying])::text[]))),
    CONSTRAINT discord_notification_history_notification_type_check CHECK (((notification_type)::text = ANY (ARRAY[('update'::character varying)::text, ('new_game'::character varying)::text, ('rating_change'::character varying)::text, ('manual'::character varying)::text])))
);


--
-- Name: discord_notification_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discord_notification_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discord_notification_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discord_notification_history_id_seq OWNED BY public.discord_notification_history.id;


--
-- Name: discord_server_configs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_server_configs (
    id bigint NOT NULL,
    discord_server_id bigint NOT NULL,
    notification_channel_id character varying(255),
    notification_format character varying(255) DEFAULT 'detailed'::character varying NOT NULL,
    custom_template text,
    include_game_description boolean DEFAULT true NOT NULL,
    include_thumbnail boolean DEFAULT true NOT NULL,
    include_ratings boolean DEFAULT true NOT NULL,
    ping_role_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    routing_rules jsonb DEFAULT '[]'::jsonb NOT NULL,
    new_game_embed jsonb,
    update_embed jsonb,
    use_embeds boolean DEFAULT false NOT NULL,
    CONSTRAINT discord_server_configs_notification_format_check CHECK (((notification_format)::text = ANY (ARRAY[('compact'::character varying)::text, ('detailed'::character varying)::text, ('custom'::character varying)::text])))
);


--
-- Name: discord_server_configs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discord_server_configs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discord_server_configs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discord_server_configs_id_seq OWNED BY public.discord_server_configs.id;


--
-- Name: discord_server_game_overrides; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_server_game_overrides (
    id bigint NOT NULL,
    discord_server_id bigint NOT NULL,
    game_id bigint NOT NULL,
    is_ignored boolean DEFAULT false NOT NULL,
    channel_id character varying(255),
    new_game_embed jsonb,
    update_embed jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: discord_server_game_overrides_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discord_server_game_overrides_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discord_server_game_overrides_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discord_server_game_overrides_id_seq OWNED BY public.discord_server_game_overrides.id;


--
-- Name: discord_server_games; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_server_games (
    id bigint NOT NULL,
    discord_server_id bigint NOT NULL,
    game_id bigint NOT NULL,
    discord_channel_id character varying(255),
    discord_message_id character varying(255),
    discord_likes jsonb DEFAULT '[]'::jsonb NOT NULL,
    discord_dislikes jsonb DEFAULT '[]'::jsonb NOT NULL,
    abbreviations jsonb DEFAULT '[]'::jsonb NOT NULL,
    discord_tags jsonb DEFAULT '[]'::jsonb NOT NULL,
    discord_updated_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    discord_payload_hash character varying(64)
);


--
-- Name: discord_server_games_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discord_server_games_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discord_server_games_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discord_server_games_id_seq OWNED BY public.discord_server_games.id;


--
-- Name: discord_server_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_server_members (
    id bigint NOT NULL,
    discord_server_id bigint NOT NULL,
    user_id bigint,
    discord_user_id character varying(255) NOT NULL,
    discord_username character varying(255) NOT NULL,
    is_admin boolean DEFAULT false NOT NULL,
    joined_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: discord_server_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discord_server_members_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discord_server_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discord_server_members_id_seq OWNED BY public.discord_server_members.id;


--
-- Name: discord_server_tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_server_tags (
    id bigint NOT NULL,
    discord_server_id bigint NOT NULL,
    tag_name character varying(255) NOT NULL,
    is_subscribed boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: discord_server_tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discord_server_tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discord_server_tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discord_server_tags_id_seq OWNED BY public.discord_server_tags.id;


--
-- Name: discord_servers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.discord_servers (
    id bigint NOT NULL,
    discord_server_id character varying(255) NOT NULL,
    discord_server_name character varying(255) NOT NULL,
    owner_user_id bigint,
    is_active boolean DEFAULT true NOT NULL,
    bot_joined_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    available_channels jsonb,
    channels_synced_at timestamp(0) without time zone
);


--
-- Name: discord_servers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.discord_servers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: discord_servers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.discord_servers_id_seq OWNED BY public.discord_servers.id;


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
-- Name: game_discord_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.game_discord_subscriptions (
    id bigint NOT NULL,
    game_id bigint NOT NULL,
    discord_server_id bigint NOT NULL,
    subscribed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: game_discord_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.game_discord_subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: game_discord_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.game_discord_subscriptions_id_seq OWNED BY public.game_discord_subscriptions.id;


--
-- Name: game_game_jam; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.game_game_jam (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_id bigint NOT NULL,
    game_jam_id bigint NOT NULL,
    ranking character varying(255),
    criteria_rankings jsonb
);


--
-- Name: game_game_jam_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.game_game_jam_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: game_game_jam_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.game_game_jam_id_seq OWNED BY public.game_game_jam.id;


--
-- Name: game_jams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.game_jams (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255) NOT NULL,
    url character varying(255) NOT NULL,
    description text,
    start_date timestamp(0) without time zone,
    end_date timestamp(0) without time zone,
    submission_count integer,
    participant_count integer,
    host character varying(255),
    needs_details_fetch boolean DEFAULT true NOT NULL
);


--
-- Name: game_jams_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.game_jams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: game_jams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.game_jams_id_seq OWNED BY public.game_jams.id;


--
-- Name: game_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.game_tag (
    id bigint NOT NULL,
    game_id bigint NOT NULL,
    tag_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: game_tag_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.game_tag_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: game_tag_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.game_tag_id_seq OWNED BY public.game_tag.id;


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
    is_latest boolean DEFAULT false NOT NULL,
    route_graph_data jsonb,
    route_graph_unreachable_data jsonb
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
    itch_id bigint,
    name public.citext NOT NULL,
    status character varying(50) DEFAULT 'In development'::character varying NOT NULL,
    is_visible boolean DEFAULT false NOT NULL,
    is_nsfw boolean DEFAULT false NOT NULL,
    description text,
    url jsonb NOT NULL,
    thumb_url character varying(255),
    game_engine character varying(50) DEFAULT 'unknown'::character varying NOT NULL,
    error text,
    authors public.citext,
    custom_tags public.citext DEFAULT ''::public.citext NOT NULL,
    uploads jsonb DEFAULT '{}'::jsonb,
    is_feedless boolean DEFAULT false NOT NULL,
    slug public.citext NOT NULL,
    source_language_id character varying(3),
    optimized_thumbnails jsonb,
    min_price numeric(10,2),
    is_on_sale boolean DEFAULT false NOT NULL,
    screenshots jsonb,
    full_description text,
    custom_css text,
    is_paid boolean DEFAULT false NOT NULL,
    has_demo boolean DEFAULT false NOT NULL,
    additional_links json,
    sale_discount_percent integer,
    first_visible_at timestamp(0) without time zone,
    has_custom_page boolean DEFAULT false NOT NULL,
    custom_description text,
    custom_screenshots json,
    custom_assets json,
    custom_page_updated_at timestamp(0) without time zone,
    custom_page_updated_by bigint,
    rating_score numeric(3,2),
    rating_count integer DEFAULT 0 NOT NULL,
    view_mode character varying(255) DEFAULT 'original'::character varying NOT NULL,
    custom_name character varying(255),
    platform character varying(255),
    steam_app_id bigint,
    content_type character varying(255) DEFAULT 'visual_novel'::character varying NOT NULL,
    developer character varying(255),
    steam_genres jsonb,
    steam_languages text,
    steam_user_tags jsonb,
    currency character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    is_delisted boolean DEFAULT false NOT NULL,
    trending_score integer DEFAULT 0 NOT NULL,
    trending_score_calculated_at timestamp(0) without time zone,
    is_stats_extraction_disabled boolean DEFAULT false NOT NULL,
    CONSTRAINT games_content_type_check CHECK (((content_type)::text = ANY (ARRAY[('visual_novel'::character varying)::text, ('adjacent'::character varying)::text, ('other'::character varying)::text]))),
    CONSTRAINT games_platform_check CHECK (((platform)::text = ANY (ARRAY[('itch_io'::character varying)::text, ('steam'::character varying)::text, ('other'::character varying)::text]))),
    CONSTRAINT games_view_mode_check CHECK (((view_mode)::text = ANY (ARRAY[('custom'::character varying)::text, ('original'::character varying)::text])))
);


--
-- Name: COLUMN games.platform; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.games.platform IS 'Platform where the game is hosted';


--
-- Name: COLUMN games.steam_app_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.games.steam_app_id IS 'Steam App ID for Steam games';


--
-- Name: COLUMN games.content_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.games.content_type IS 'Content type: visual_novel (listed on fvn.li), adjacent (related games), other (non-FVN)';


--
-- Name: COLUMN games.developer; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.games.developer IS 'Game developer(s)';


--
-- Name: COLUMN games.steam_genres; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.games.steam_genres IS 'Steam genre tags';


--
-- Name: COLUMN games.steam_languages; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.games.steam_languages IS 'Supported languages from Steam';


--
-- Name: COLUMN games.steam_user_tags; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.games.steam_user_tags IS 'User-defined tags from Steam';


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
-- Name: import_states; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.import_states (
    id bigint NOT NULL,
    type character varying(255) NOT NULL,
    last_processed_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: import_states_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.import_states_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: import_states_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.import_states_id_seq OWNED BY public.import_states.id;


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
-- Name: language_mappings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.language_mappings (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_language_key character varying(50) NOT NULL,
    iso_code character varying(3) NOT NULL,
    game_id bigint
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
-- Name: monitored_scheduled_task_log_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.monitored_scheduled_task_log_items (
    id bigint NOT NULL,
    monitored_scheduled_task_id bigint CONSTRAINT monitored_scheduled_task_lo_monitored_scheduled_task_i_not_null NOT NULL,
    type character varying(255) NOT NULL,
    meta json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: monitored_scheduled_task_log_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.monitored_scheduled_task_log_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: monitored_scheduled_task_log_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.monitored_scheduled_task_log_items_id_seq OWNED BY public.monitored_scheduled_task_log_items.id;


--
-- Name: monitored_scheduled_tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.monitored_scheduled_tasks (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255),
    cron_expression character varying(255) NOT NULL,
    timezone character varying(255),
    ping_url character varying(255),
    last_started_at timestamp(0) without time zone,
    last_finished_at timestamp(0) without time zone,
    last_failed_at timestamp(0) without time zone,
    last_skipped_at timestamp(0) without time zone,
    registered_on_oh_dear_at timestamp(0) without time zone,
    last_pinged_at timestamp(0) without time zone,
    grace_time_in_minutes integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: monitored_scheduled_tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.monitored_scheduled_tasks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: monitored_scheduled_tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.monitored_scheduled_tasks_id_seq OWNED BY public.monitored_scheduled_tasks.id;


--
-- Name: notification_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_history (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    success boolean DEFAULT true NOT NULL,
    meta_data text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT notification_history_type_check CHECK (((type)::text = ANY (ARRAY[('discord'::character varying)::text, ('telegram'::character varying)::text, ('email'::character varying)::text, ('browser'::character varying)::text])))
);


--
-- Name: notification_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_history_id_seq OWNED BY public.notification_history.id;


--
-- Name: notification_queue; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notification_queue (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    channel character varying(255) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    scheduled_at timestamp(0) without time zone,
    processed_at timestamp(0) without time zone,
    payload json,
    error text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    meta_data jsonb,
    CONSTRAINT notification_queue_channel_check CHECK (((channel)::text = ANY (ARRAY[('browser'::character varying)::text, ('discord'::character varying)::text, ('email'::character varying)::text]))),
    CONSTRAINT notification_queue_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('processing'::character varying)::text, ('sent'::character varying)::text, ('failed'::character varying)::text])))
);


--
-- Name: notification_queue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notification_queue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notification_queue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notification_queue_id_seq OWNED BY public.notification_queue.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


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
    id integer NOT NULL,
    event_id integer NOT NULL,
    game_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: processed_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.processed_events_id_seq
    AS integer
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
-- Name: push_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.push_subscriptions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    endpoint character varying(500) NOT NULL,
    p256dh character varying(255) NOT NULL,
    auth character varying(255) NOT NULL,
    subscription_data json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: push_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.push_subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: push_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.push_subscriptions_id_seq OWNED BY public.push_subscriptions.id;


--
-- Name: raters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.raters (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    itch_id bigint,
    name public.citext NOT NULL,
    username public.citext,
    is_suspicious boolean DEFAULT false NOT NULL,
    suspicion_reason character varying(255),
    marked_suspicious_at timestamp(0) without time zone,
    steam_id character varying(255),
    external_platform character varying(255),
    is_review_banned boolean DEFAULT false NOT NULL
);


--
-- Name: COLUMN raters.steam_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.raters.steam_id IS 'Steam user ID (SteamID64)';


--
-- Name: COLUMN raters.external_platform; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.raters.external_platform IS 'Primary external platform for this rater';


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
    event_id bigint,
    game_id bigint NOT NULL,
    rater_id bigint,
    rating smallint NOT NULL,
    review text NOT NULL,
    is_visible boolean DEFAULT true NOT NULL,
    is_reviewed boolean DEFAULT false NOT NULL,
    external_id character varying(255),
    source_platform character varying(255) NOT NULL,
    external_metadata jsonb,
    user_id bigint,
    has_spoilers boolean DEFAULT false NOT NULL,
    CONSTRAINT ratings_source_platform_check CHECK (((source_platform)::text = ANY ((ARRAY['itch_io'::character varying, 'steam'::character varying, 'fvn_li'::character varying])::text[])))
);


--
-- Name: COLUMN ratings.external_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ratings.external_id IS 'External platform review ID (e.g., Steam recommendationid)';


--
-- Name: COLUMN ratings.source_platform; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ratings.source_platform IS 'Platform where the review originated';


--
-- Name: COLUMN ratings.external_metadata; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ratings.external_metadata IS 'Additional metadata from external platforms (playtime, votes, etc.)';


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
-- Name: review_reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.review_reports (
    id bigint NOT NULL,
    rating_id bigint NOT NULL,
    reporter_id bigint NOT NULL,
    reason character varying(255) NOT NULL,
    details text,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    reviewed_by bigint,
    reviewed_at timestamp(0) without time zone,
    admin_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    discord_notified_at timestamp(0) without time zone
);


--
-- Name: review_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.review_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: review_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.review_reports_id_seq OWNED BY public.review_reports.id;


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
-- Name: social_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.social_accounts (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    provider_name character varying(255) NOT NULL,
    provider_id character varying(255) NOT NULL,
    token text,
    refresh_token character varying(255),
    token_expires_at timestamp(0) without time zone,
    provider_data json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    itchio_game_ids json
);


--
-- Name: social_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.social_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: social_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.social_accounts_id_seq OWNED BY public.social_accounts.id;


--
-- Name: tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tags (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tags_id_seq OWNED BY public.tags.id;


--
-- Name: unique_dialogue_texts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.unique_dialogue_texts (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    text_hash character varying(32) NOT NULL,
    text_content text NOT NULL,
    search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english'::regconfig, text_content)) STORED NOT NULL
);


--
-- Name: COLUMN unique_dialogue_texts.text_hash; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.unique_dialogue_texts.text_hash IS 'MD5 hash of the text for quick lookups';


--
-- Name: unique_dialogue_texts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.unique_dialogue_texts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: unique_dialogue_texts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.unique_dialogue_texts_id_seq OWNED BY public.unique_dialogue_texts.id;


--
-- Name: user_game_progress; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_game_progress (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_id bigint NOT NULL,
    game_version_id bigint,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    personal_notes text,
    status character varying(255) DEFAULT 'reading'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    receive_updates boolean DEFAULT false NOT NULL,
    CONSTRAINT user_game_progress_status_check CHECK (((status)::text = ANY (ARRAY[('reading'::character varying)::text, ('completed'::character varying)::text, ('plan_to_read'::character varying)::text, ('on_hold'::character varying)::text, ('dropped'::character varying)::text, ('custom'::character varying)::text])))
);


--
-- Name: user_game_progress_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_game_progress_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_game_progress_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_game_progress_id_seq OWNED BY public.user_game_progress.id;


--
-- Name: user_ignored_games; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_ignored_games (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    game_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_ignored_games_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_ignored_games_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_ignored_games_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_ignored_games_id_seq OWNED BY public.user_ignored_games.id;


--
-- Name: user_notification_preferences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_notification_preferences (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    discord_notifications_enabled boolean DEFAULT false CONSTRAINT user_notification_preferenc_discord_notifications_enab_not_null NOT NULL,
    browser_notifications_enabled boolean DEFAULT false CONSTRAINT user_notification_preferenc_browser_notifications_enab_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    notification_digest character varying(255) DEFAULT 'asap'::character varying NOT NULL,
    CONSTRAINT user_notification_preferences_notification_digest_check CHECK (((notification_digest)::text = ANY (ARRAY[('asap'::character varying)::text, ('daily'::character varying)::text, ('weekly'::character varying)::text])))
);


--
-- Name: user_notification_preferences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_notification_preferences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_notification_preferences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_notification_preferences_id_seq OWNED BY public.user_notification_preferences.id;


--
-- Name: user_preferences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_preferences (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    preferred_languages jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    excluded_tags jsonb
);


--
-- Name: user_preferences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_preferences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_preferences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_preferences_id_seq OWNED BY public.user_preferences.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255),
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    avatar character varying(255),
    is_admin boolean DEFAULT false NOT NULL,
    is_review_banned boolean DEFAULT false NOT NULL
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
-- Name: version_dialogue_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_dialogue_lines (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    character_id bigint,
    iso_code character varying(10) NOT NULL,
    file_path character varying(255) NOT NULL,
    line_number integer NOT NULL,
    text_id bigint,
    context character varying(255)
);


--
-- Name: version_dialogue_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_dialogue_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_dialogue_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_dialogue_lines_id_seq OWNED BY public.version_dialogue_lines.id;


--
-- Name: version_file_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_file_categories (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    category character varying(20) NOT NULL,
    total_count integer NOT NULL,
    total_size bigint NOT NULL
);


--
-- Name: version_file_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_file_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_file_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_file_categories_id_seq OWNED BY public.version_file_categories.id;


--
-- Name: version_file_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_file_types (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    version_file_category_id bigint NOT NULL,
    extension character varying(100) NOT NULL,
    count integer NOT NULL,
    size bigint NOT NULL
);


--
-- Name: version_file_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_file_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_file_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_file_types_id_seq OWNED BY public.version_file_types.id;


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
    words integer NOT NULL,
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
-- Name: version_route_edges; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_route_edges (
    id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    from_label character varying(255) NOT NULL,
    to_label character varying(255) NOT NULL,
    edge_type character varying(20) DEFAULT 'flow'::character varying NOT NULL,
    condition text,
    file_path character varying(255),
    line_number integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: version_route_edges_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_route_edges_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_route_edges_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_route_edges_id_seq OWNED BY public.version_route_edges.id;


--
-- Name: version_route_labels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_route_labels (
    id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    file_path character varying(255) NOT NULL,
    line_number integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_ending boolean DEFAULT false NOT NULL,
    returns_to_caller boolean DEFAULT false NOT NULL,
    externally_invoked boolean DEFAULT false NOT NULL,
    is_scaffolding boolean DEFAULT false NOT NULL
);


--
-- Name: version_route_labels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_route_labels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_route_labels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_route_labels_id_seq OWNED BY public.version_route_labels.id;


--
-- Name: version_route_menu_choices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_route_menu_choices (
    id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    from_label character varying(255) NOT NULL,
    text text,
    condition text,
    target_label character varying(255),
    edge_type character varying(20),
    file_path character varying(255),
    line_number integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    prompt text,
    translations json,
    prompt_translations json,
    menu_line integer DEFAULT 0 NOT NULL,
    enclosing_condition text,
    choice_condition text,
    menu_branch text,
    parent_menu_line integer DEFAULT 0 NOT NULL,
    parent_choice_line integer DEFAULT 0 NOT NULL,
    menu_condition_stack json
);


--
-- Name: version_route_menu_choices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_route_menu_choices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_route_menu_choices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_route_menu_choices_id_seq OWNED BY public.version_route_menu_choices.id;


--
-- Name: version_route_paths; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_route_paths (
    id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    ending_label character varying(255) NOT NULL,
    path_labels json NOT NULL,
    step_count integer NOT NULL,
    word_count integer NOT NULL,
    choice_count integer NOT NULL,
    choices json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: version_route_paths_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_route_paths_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_route_paths_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_route_paths_id_seq OWNED BY public.version_route_paths.id;


--
-- Name: version_route_variable_changes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_route_variable_changes (
    id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    label character varying(255) NOT NULL,
    variable_name character varying(255) NOT NULL,
    operation character varying(10) DEFAULT '='::character varying NOT NULL,
    value text,
    file_path character varying(255),
    line_number integer DEFAULT 0 NOT NULL,
    context text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    condition text,
    condition_stack json
);


--
-- Name: version_route_variable_changes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_route_variable_changes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_route_variable_changes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_route_variable_changes_id_seq OWNED BY public.version_route_variable_changes.id;


--
-- Name: version_route_variables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_route_variables (
    id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    default_value text,
    type character varying(20) DEFAULT 'default'::character varying NOT NULL,
    file_path character varying(255),
    line_number integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: version_route_variables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_route_variables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_route_variables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_route_variables_id_seq OWNED BY public.version_route_variables.id;


--
-- Name: version_supported_languages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_supported_languages (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    game_version_id bigint NOT NULL,
    iso_code character varying(3) NOT NULL,
    is_available boolean DEFAULT true NOT NULL
);


--
-- Name: version_supported_languages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_supported_languages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_supported_languages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_supported_languages_id_seq OWNED BY public.version_supported_languages.id;


--
-- Name: version_word_frequencies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.version_word_frequencies (
    id bigint NOT NULL,
    game_version_id bigint NOT NULL,
    iso_code character varying(3) NOT NULL,
    word_data json NOT NULL,
    calculated_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: version_word_frequencies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.version_word_frequencies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: version_word_frequencies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.version_word_frequencies_id_seq OWNED BY public.version_word_frequencies.id;


--
-- Name: vn_list_entries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vn_list_entries (
    id bigint NOT NULL,
    vn_list_id bigint NOT NULL,
    game_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sort_order integer DEFAULT 0 NOT NULL,
    private_notes text
);


--
-- Name: vn_list_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vn_list_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vn_list_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vn_list_entries_id_seq OWNED BY public.vn_list_entries.id;


--
-- Name: vn_lists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vn_lists (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    is_default boolean DEFAULT false NOT NULL,
    is_public boolean DEFAULT false NOT NULL,
    type character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vn_lists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vn_lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vn_lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vn_lists_id_seq OWNED BY public.vn_lists.id;


--
-- Name: change_logs_y2025m01; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m01 FOR VALUES FROM ('2025-01-01 00:00:00+00') TO ('2025-02-01 00:00:00+00');


--
-- Name: change_logs_y2025m02; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m02 FOR VALUES FROM ('2025-02-01 00:00:00+00') TO ('2025-03-01 00:00:00+00');


--
-- Name: change_logs_y2025m03; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m03 FOR VALUES FROM ('2025-03-01 00:00:00+00') TO ('2025-04-01 00:00:00+00');


--
-- Name: change_logs_y2025m04; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m04 FOR VALUES FROM ('2025-04-01 00:00:00+00') TO ('2025-05-01 00:00:00+00');


--
-- Name: change_logs_y2025m05; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m05 FOR VALUES FROM ('2025-05-01 00:00:00+00') TO ('2025-06-01 00:00:00+00');


--
-- Name: change_logs_y2025m06; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m06 FOR VALUES FROM ('2025-06-01 00:00:00+00') TO ('2025-07-01 00:00:00+00');


--
-- Name: change_logs_y2025m07; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m07 FOR VALUES FROM ('2025-07-01 00:00:00+00') TO ('2025-08-01 00:00:00+00');


--
-- Name: change_logs_y2025m08; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m08 FOR VALUES FROM ('2025-08-01 00:00:00+00') TO ('2025-09-01 00:00:00+00');


--
-- Name: change_logs_y2025m09; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m09 FOR VALUES FROM ('2025-09-01 00:00:00+00') TO ('2025-10-01 00:00:00+00');


--
-- Name: change_logs_y2025m10; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m10 FOR VALUES FROM ('2025-10-01 00:00:00+00') TO ('2025-11-01 00:00:00+00');


--
-- Name: change_logs_y2025m11; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m11 FOR VALUES FROM ('2025-11-01 00:00:00+00') TO ('2025-12-01 00:00:00+00');


--
-- Name: change_logs_y2025m12; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2025m12 FOR VALUES FROM ('2025-12-01 00:00:00+00') TO ('2026-01-01 00:00:00+00');


--
-- Name: change_logs_y2026m01; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m01 FOR VALUES FROM ('2026-01-01 00:00:00+00') TO ('2026-02-01 00:00:00+00');


--
-- Name: change_logs_y2026m02; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m02 FOR VALUES FROM ('2026-02-01 00:00:00+00') TO ('2026-03-01 00:00:00+00');


--
-- Name: change_logs_y2026m03; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m03 FOR VALUES FROM ('2026-03-01 00:00:00+00') TO ('2026-04-01 00:00:00+00');


--
-- Name: change_logs_y2026m04; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m04 FOR VALUES FROM ('2026-04-01 00:00:00+00') TO ('2026-05-01 00:00:00+00');


--
-- Name: change_logs_y2026m05; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m05 FOR VALUES FROM ('2026-05-01 00:00:00+00') TO ('2026-06-01 00:00:00+00');


--
-- Name: change_logs_y2026m06; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m06 FOR VALUES FROM ('2026-06-01 00:00:00+00') TO ('2026-07-01 00:00:00+00');


--
-- Name: change_logs_y2026m07; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m07 FOR VALUES FROM ('2026-07-01 00:00:00+00') TO ('2026-08-01 00:00:00+00');


--
-- Name: change_logs_y2026m08; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m08 FOR VALUES FROM ('2026-08-01 00:00:00+00') TO ('2026-09-01 00:00:00+00');


--
-- Name: change_logs_y2026m09; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m09 FOR VALUES FROM ('2026-09-01 00:00:00+00') TO ('2026-10-01 00:00:00+00');


--
-- Name: change_logs_y2026m10; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m10 FOR VALUES FROM ('2026-10-01 00:00:00+00') TO ('2026-11-01 00:00:00+00');


--
-- Name: change_logs_y2026m11; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m11 FOR VALUES FROM ('2026-11-01 00:00:00+00') TO ('2026-12-01 00:00:00+00');


--
-- Name: change_logs_y2026m12; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2026m12 FOR VALUES FROM ('2026-12-01 00:00:00+00') TO ('2027-01-01 00:00:00+00');


--
-- Name: change_logs_y2027m01; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m01 FOR VALUES FROM ('2027-01-01 00:00:00+00') TO ('2027-02-01 00:00:00+00');


--
-- Name: change_logs_y2027m02; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m02 FOR VALUES FROM ('2027-02-01 00:00:00+00') TO ('2027-03-01 00:00:00+00');


--
-- Name: change_logs_y2027m03; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m03 FOR VALUES FROM ('2027-03-01 00:00:00+00') TO ('2027-04-01 00:00:00+00');


--
-- Name: change_logs_y2027m04; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m04 FOR VALUES FROM ('2027-04-01 00:00:00+00') TO ('2027-05-01 00:00:00+00');


--
-- Name: change_logs_y2027m05; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m05 FOR VALUES FROM ('2027-05-01 00:00:00+00') TO ('2027-06-01 00:00:00+00');


--
-- Name: change_logs_y2027m06; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m06 FOR VALUES FROM ('2027-06-01 00:00:00+00') TO ('2027-07-01 00:00:00+00');


--
-- Name: change_logs_y2027m07; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m07 FOR VALUES FROM ('2027-07-01 00:00:00+00') TO ('2027-08-01 00:00:00+00');


--
-- Name: change_logs_y2027m08; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m08 FOR VALUES FROM ('2027-08-01 00:00:00+00') TO ('2027-09-01 00:00:00+00');


--
-- Name: change_logs_y2027m09; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m09 FOR VALUES FROM ('2027-09-01 00:00:00+00') TO ('2027-10-01 00:00:00+00');


--
-- Name: change_logs_y2027m10; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m10 FOR VALUES FROM ('2027-10-01 00:00:00+00') TO ('2027-11-01 00:00:00+00');


--
-- Name: change_logs_y2027m11; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m11 FOR VALUES FROM ('2027-11-01 00:00:00+00') TO ('2027-12-01 00:00:00+00');


--
-- Name: change_logs_y2027m12; Type: TABLE ATTACH; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ATTACH PARTITION public.change_logs_y2027m12 FOR VALUES FROM ('2027-12-01 00:00:00+00') TO ('2028-01-01 00:00:00+00');


--
-- Name: addition_request_users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_request_users ALTER COLUMN id SET DEFAULT nextval('public.addition_request_users_id_seq'::regclass);


--
-- Name: addition_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_requests ALTER COLUMN id SET DEFAULT nextval('public.addition_requests_id_seq'::regclass);


--
-- Name: android_builds id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.android_builds ALTER COLUMN id SET DEFAULT nextval('public.android_builds_id_seq'::regclass);


--
-- Name: bug_report_comments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bug_report_comments ALTER COLUMN id SET DEFAULT nextval('public.bug_report_comments_id_seq'::regclass);


--
-- Name: bug_reports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bug_reports ALTER COLUMN id SET DEFAULT nextval('public.bug_reports_id_seq'::regclass);


--
-- Name: change_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs ALTER COLUMN id SET DEFAULT nextval('public.change_logs_id_seq'::regclass);


--
-- Name: characters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters ALTER COLUMN id SET DEFAULT nextval('public.characters_id_seq'::regclass);


--
-- Name: click_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.click_stats ALTER COLUMN id SET DEFAULT nextval('public.click_stats_id_seq'::regclass);


--
-- Name: discord_channel_announcements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_channel_announcements ALTER COLUMN id SET DEFAULT nextval('public.discord_channel_announcements_id_seq'::regclass);


--
-- Name: discord_notification_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_notification_history ALTER COLUMN id SET DEFAULT nextval('public.discord_notification_history_id_seq'::regclass);


--
-- Name: discord_server_configs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_configs ALTER COLUMN id SET DEFAULT nextval('public.discord_server_configs_id_seq'::regclass);


--
-- Name: discord_server_game_overrides id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_game_overrides ALTER COLUMN id SET DEFAULT nextval('public.discord_server_game_overrides_id_seq'::regclass);


--
-- Name: discord_server_games id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_games ALTER COLUMN id SET DEFAULT nextval('public.discord_server_games_id_seq'::regclass);


--
-- Name: discord_server_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_members ALTER COLUMN id SET DEFAULT nextval('public.discord_server_members_id_seq'::regclass);


--
-- Name: discord_server_tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_tags ALTER COLUMN id SET DEFAULT nextval('public.discord_server_tags_id_seq'::regclass);


--
-- Name: discord_servers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_servers ALTER COLUMN id SET DEFAULT nextval('public.discord_servers_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: game_discord_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_discord_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.game_discord_subscriptions_id_seq'::regclass);


--
-- Name: game_game_jam id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam ALTER COLUMN id SET DEFAULT nextval('public.game_game_jam_id_seq'::regclass);


--
-- Name: game_jams id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_jams ALTER COLUMN id SET DEFAULT nextval('public.game_jams_id_seq'::regclass);


--
-- Name: game_tag id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_tag ALTER COLUMN id SET DEFAULT nextval('public.game_tag_id_seq'::regclass);


--
-- Name: game_versions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions ALTER COLUMN id SET DEFAULT nextval('public.game_versions_id_seq'::regclass);


--
-- Name: games id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games ALTER COLUMN id SET DEFAULT nextval('public.games_id_seq'::regclass);


--
-- Name: import_states id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_states ALTER COLUMN id SET DEFAULT nextval('public.import_states_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: language_mappings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings ALTER COLUMN id SET DEFAULT nextval('public.language_mappings_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: monitored_scheduled_task_log_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_task_log_items ALTER COLUMN id SET DEFAULT nextval('public.monitored_scheduled_task_log_items_id_seq'::regclass);


--
-- Name: monitored_scheduled_tasks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_tasks ALTER COLUMN id SET DEFAULT nextval('public.monitored_scheduled_tasks_id_seq'::regclass);


--
-- Name: notification_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history ALTER COLUMN id SET DEFAULT nextval('public.notification_history_id_seq'::regclass);


--
-- Name: notification_queue id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue ALTER COLUMN id SET DEFAULT nextval('public.notification_queue_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: processed_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.processed_events ALTER COLUMN id SET DEFAULT nextval('public.processed_events_id_seq'::regclass);


--
-- Name: push_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.push_subscriptions_id_seq'::regclass);


--
-- Name: raters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters ALTER COLUMN id SET DEFAULT nextval('public.raters_id_seq'::regclass);


--
-- Name: ratings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings ALTER COLUMN id SET DEFAULT nextval('public.ratings_id_seq'::regclass);


--
-- Name: review_reports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.review_reports ALTER COLUMN id SET DEFAULT nextval('public.review_reports_id_seq'::regclass);


--
-- Name: social_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_accounts ALTER COLUMN id SET DEFAULT nextval('public.social_accounts_id_seq'::regclass);


--
-- Name: tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags ALTER COLUMN id SET DEFAULT nextval('public.tags_id_seq'::regclass);


--
-- Name: unique_dialogue_texts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unique_dialogue_texts ALTER COLUMN id SET DEFAULT nextval('public.unique_dialogue_texts_id_seq'::regclass);


--
-- Name: user_game_progress id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress ALTER COLUMN id SET DEFAULT nextval('public.user_game_progress_id_seq'::regclass);


--
-- Name: user_ignored_games id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ignored_games ALTER COLUMN id SET DEFAULT nextval('public.user_ignored_games_id_seq'::regclass);


--
-- Name: user_notification_preferences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_preferences ALTER COLUMN id SET DEFAULT nextval('public.user_notification_preferences_id_seq'::regclass);


--
-- Name: user_preferences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_preferences ALTER COLUMN id SET DEFAULT nextval('public.user_preferences_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: version_character_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats ALTER COLUMN id SET DEFAULT nextval('public.character_version_stats_id_seq'::regclass);


--
-- Name: version_dialogue_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_dialogue_lines ALTER COLUMN id SET DEFAULT nextval('public.version_dialogue_lines_id_seq'::regclass);


--
-- Name: version_file_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_categories ALTER COLUMN id SET DEFAULT nextval('public.version_file_categories_id_seq'::regclass);


--
-- Name: version_file_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_types ALTER COLUMN id SET DEFAULT nextval('public.version_file_types_id_seq'::regclass);


--
-- Name: version_language_stats id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats ALTER COLUMN id SET DEFAULT nextval('public.version_language_stats_id_seq'::regclass);


--
-- Name: version_route_edges id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_edges ALTER COLUMN id SET DEFAULT nextval('public.version_route_edges_id_seq'::regclass);


--
-- Name: version_route_labels id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_labels ALTER COLUMN id SET DEFAULT nextval('public.version_route_labels_id_seq'::regclass);


--
-- Name: version_route_menu_choices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_menu_choices ALTER COLUMN id SET DEFAULT nextval('public.version_route_menu_choices_id_seq'::regclass);


--
-- Name: version_route_paths id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_paths ALTER COLUMN id SET DEFAULT nextval('public.version_route_paths_id_seq'::regclass);


--
-- Name: version_route_variable_changes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_variable_changes ALTER COLUMN id SET DEFAULT nextval('public.version_route_variable_changes_id_seq'::regclass);


--
-- Name: version_route_variables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_variables ALTER COLUMN id SET DEFAULT nextval('public.version_route_variables_id_seq'::regclass);


--
-- Name: version_supported_languages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages ALTER COLUMN id SET DEFAULT nextval('public.version_supported_languages_id_seq'::regclass);


--
-- Name: version_word_frequencies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_word_frequencies ALTER COLUMN id SET DEFAULT nextval('public.version_word_frequencies_id_seq'::regclass);


--
-- Name: vn_list_entries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries ALTER COLUMN id SET DEFAULT nextval('public.vn_list_entries_id_seq'::regclass);


--
-- Name: vn_lists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_lists ALTER COLUMN id SET DEFAULT nextval('public.vn_lists_id_seq'::regclass);


--
-- Name: addition_request_users addition_request_users_addition_request_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_request_users
    ADD CONSTRAINT addition_request_users_addition_request_id_user_id_unique UNIQUE (addition_request_id, user_id);


--
-- Name: addition_request_users addition_request_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_request_users
    ADD CONSTRAINT addition_request_users_pkey PRIMARY KEY (id);


--
-- Name: addition_requests addition_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_requests
    ADD CONSTRAINT addition_requests_pkey PRIMARY KEY (id);


--
-- Name: android_builds android_builds_build_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.android_builds
    ADD CONSTRAINT android_builds_build_id_unique UNIQUE (build_id);


--
-- Name: android_builds android_builds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.android_builds
    ADD CONSTRAINT android_builds_pkey PRIMARY KEY (id);


--
-- Name: bug_report_comments bug_report_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bug_report_comments
    ADD CONSTRAINT bug_report_comments_pkey PRIMARY KEY (id);


--
-- Name: bug_reports bug_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bug_reports
    ADD CONSTRAINT bug_reports_pkey PRIMARY KEY (id);


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
-- Name: change_logs change_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs
    ADD CONSTRAINT change_logs_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m01 change_logs_y2025m01_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m01
    ADD CONSTRAINT change_logs_y2025m01_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m02 change_logs_y2025m02_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m02
    ADD CONSTRAINT change_logs_y2025m02_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m03 change_logs_y2025m03_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m03
    ADD CONSTRAINT change_logs_y2025m03_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m04 change_logs_y2025m04_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m04
    ADD CONSTRAINT change_logs_y2025m04_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m05 change_logs_y2025m05_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m05
    ADD CONSTRAINT change_logs_y2025m05_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m06 change_logs_y2025m06_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m06
    ADD CONSTRAINT change_logs_y2025m06_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m07 change_logs_y2025m07_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m07
    ADD CONSTRAINT change_logs_y2025m07_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m08 change_logs_y2025m08_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m08
    ADD CONSTRAINT change_logs_y2025m08_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m09 change_logs_y2025m09_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m09
    ADD CONSTRAINT change_logs_y2025m09_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m10 change_logs_y2025m10_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m10
    ADD CONSTRAINT change_logs_y2025m10_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m11 change_logs_y2025m11_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m11
    ADD CONSTRAINT change_logs_y2025m11_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2025m12 change_logs_y2025m12_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2025m12
    ADD CONSTRAINT change_logs_y2025m12_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m01 change_logs_y2026m01_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m01
    ADD CONSTRAINT change_logs_y2026m01_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m02 change_logs_y2026m02_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m02
    ADD CONSTRAINT change_logs_y2026m02_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m03 change_logs_y2026m03_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m03
    ADD CONSTRAINT change_logs_y2026m03_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m04 change_logs_y2026m04_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m04
    ADD CONSTRAINT change_logs_y2026m04_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m05 change_logs_y2026m05_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m05
    ADD CONSTRAINT change_logs_y2026m05_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m06 change_logs_y2026m06_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m06
    ADD CONSTRAINT change_logs_y2026m06_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m07 change_logs_y2026m07_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m07
    ADD CONSTRAINT change_logs_y2026m07_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m08 change_logs_y2026m08_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m08
    ADD CONSTRAINT change_logs_y2026m08_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m09 change_logs_y2026m09_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m09
    ADD CONSTRAINT change_logs_y2026m09_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m10 change_logs_y2026m10_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m10
    ADD CONSTRAINT change_logs_y2026m10_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m11 change_logs_y2026m11_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m11
    ADD CONSTRAINT change_logs_y2026m11_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2026m12 change_logs_y2026m12_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2026m12
    ADD CONSTRAINT change_logs_y2026m12_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m01 change_logs_y2027m01_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m01
    ADD CONSTRAINT change_logs_y2027m01_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m02 change_logs_y2027m02_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m02
    ADD CONSTRAINT change_logs_y2027m02_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m03 change_logs_y2027m03_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m03
    ADD CONSTRAINT change_logs_y2027m03_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m04 change_logs_y2027m04_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m04
    ADD CONSTRAINT change_logs_y2027m04_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m05 change_logs_y2027m05_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m05
    ADD CONSTRAINT change_logs_y2027m05_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m06 change_logs_y2027m06_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m06
    ADD CONSTRAINT change_logs_y2027m06_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m07 change_logs_y2027m07_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m07
    ADD CONSTRAINT change_logs_y2027m07_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m08 change_logs_y2027m08_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m08
    ADD CONSTRAINT change_logs_y2027m08_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m09 change_logs_y2027m09_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m09
    ADD CONSTRAINT change_logs_y2027m09_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m10 change_logs_y2027m10_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m10
    ADD CONSTRAINT change_logs_y2027m10_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m11 change_logs_y2027m11_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m11
    ADD CONSTRAINT change_logs_y2027m11_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: change_logs_y2027m12 change_logs_y2027m12_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.change_logs_y2027m12
    ADD CONSTRAINT change_logs_y2027m12_pkey PRIMARY KEY (id, "timestamp");


--
-- Name: version_character_stats character_version_stats_game_version_id_character_id_iso_code_u; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT character_version_stats_game_version_id_character_id_iso_code_u UNIQUE (game_version_id, character_id, iso_code);


--
-- Name: version_character_stats character_version_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT character_version_stats_pkey PRIMARY KEY (id);


--
-- Name: characters characters_game_id_character_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_game_id_character_id_unique UNIQUE (game_id, character_id);


--
-- Name: characters characters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_pkey PRIMARY KEY (id);


--
-- Name: click_stats click_stats_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.click_stats
    ADD CONSTRAINT click_stats_pkey PRIMARY KEY (id);


--
-- Name: discord_channel_announcements discord_channel_announcements_game_version_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_channel_announcements
    ADD CONSTRAINT discord_channel_announcements_game_version_id_unique UNIQUE (game_version_id);


--
-- Name: discord_channel_announcements discord_channel_announcements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_channel_announcements
    ADD CONSTRAINT discord_channel_announcements_pkey PRIMARY KEY (id);


--
-- Name: discord_notification_history discord_notification_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_notification_history
    ADD CONSTRAINT discord_notification_history_pkey PRIMARY KEY (id);


--
-- Name: discord_server_configs discord_server_configs_discord_server_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_configs
    ADD CONSTRAINT discord_server_configs_discord_server_id_unique UNIQUE (discord_server_id);


--
-- Name: discord_server_configs discord_server_configs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_configs
    ADD CONSTRAINT discord_server_configs_pkey PRIMARY KEY (id);


--
-- Name: discord_server_game_overrides discord_server_game_overrides_discord_server_id_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_game_overrides
    ADD CONSTRAINT discord_server_game_overrides_discord_server_id_game_id_unique UNIQUE (discord_server_id, game_id);


--
-- Name: discord_server_game_overrides discord_server_game_overrides_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_game_overrides
    ADD CONSTRAINT discord_server_game_overrides_pkey PRIMARY KEY (id);


--
-- Name: discord_server_games discord_server_games_discord_server_id_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_games
    ADD CONSTRAINT discord_server_games_discord_server_id_game_id_unique UNIQUE (discord_server_id, game_id);


--
-- Name: discord_server_games discord_server_games_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_games
    ADD CONSTRAINT discord_server_games_pkey PRIMARY KEY (id);


--
-- Name: discord_server_members discord_server_members_discord_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_members
    ADD CONSTRAINT discord_server_members_discord_user_id_unique UNIQUE (discord_user_id);


--
-- Name: discord_server_members discord_server_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_members
    ADD CONSTRAINT discord_server_members_pkey PRIMARY KEY (id);


--
-- Name: discord_server_tags discord_server_tags_discord_server_id_tag_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_tags
    ADD CONSTRAINT discord_server_tags_discord_server_id_tag_name_unique UNIQUE (discord_server_id, tag_name);


--
-- Name: discord_server_tags discord_server_tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_tags
    ADD CONSTRAINT discord_server_tags_pkey PRIMARY KEY (id);


--
-- Name: discord_servers discord_servers_discord_server_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_servers
    ADD CONSTRAINT discord_servers_discord_server_id_unique UNIQUE (discord_server_id);


--
-- Name: discord_servers discord_servers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_servers
    ADD CONSTRAINT discord_servers_pkey PRIMARY KEY (id);


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
-- Name: game_discord_subscriptions game_discord_subscriptions_game_id_discord_server_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_discord_subscriptions
    ADD CONSTRAINT game_discord_subscriptions_game_id_discord_server_id_unique UNIQUE (game_id, discord_server_id);


--
-- Name: game_discord_subscriptions game_discord_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_discord_subscriptions
    ADD CONSTRAINT game_discord_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: game_game_jam game_game_jam_game_id_game_jam_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam
    ADD CONSTRAINT game_game_jam_game_id_game_jam_id_unique UNIQUE (game_id, game_jam_id);


--
-- Name: game_game_jam game_game_jam_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam
    ADD CONSTRAINT game_game_jam_pkey PRIMARY KEY (id);


--
-- Name: game_jams game_jams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_jams
    ADD CONSTRAINT game_jams_pkey PRIMARY KEY (id);


--
-- Name: game_jams game_jams_url_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_jams
    ADD CONSTRAINT game_jams_url_unique UNIQUE (url);


--
-- Name: game_tag game_tag_game_id_tag_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_tag
    ADD CONSTRAINT game_tag_game_id_tag_id_unique UNIQUE (game_id, tag_id);


--
-- Name: game_tag game_tag_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_tag
    ADD CONSTRAINT game_tag_pkey PRIMARY KEY (id);


--
-- Name: game_versions game_versions_game_id_version_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions
    ADD CONSTRAINT game_versions_game_id_version_unique UNIQUE (game_id, version);


--
-- Name: game_versions game_versions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions
    ADD CONSTRAINT game_versions_pkey PRIMARY KEY (id);


--
-- Name: games games_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_game_id_unique UNIQUE (itch_id);


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
-- Name: import_states import_states_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_states
    ADD CONSTRAINT import_states_pkey PRIMARY KEY (id);


--
-- Name: import_states import_states_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.import_states
    ADD CONSTRAINT import_states_type_unique UNIQUE (type);


--
-- Name: iso_639_3_languages iso_639_3_languages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.iso_639_3_languages
    ADD CONSTRAINT iso_639_3_languages_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: language_mappings language_mappings_game_id_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings
    ADD CONSTRAINT language_mappings_game_id_key_unique UNIQUE (game_id, game_language_key);


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
-- Name: monitored_scheduled_task_log_items monitored_scheduled_task_log_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_task_log_items
    ADD CONSTRAINT monitored_scheduled_task_log_items_pkey PRIMARY KEY (id);


--
-- Name: monitored_scheduled_tasks monitored_scheduled_tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_tasks
    ADD CONSTRAINT monitored_scheduled_tasks_pkey PRIMARY KEY (id);


--
-- Name: notification_history notification_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_pkey PRIMARY KEY (id);


--
-- Name: notification_history notification_history_unique_constraint; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_unique_constraint UNIQUE (user_id, game_id, game_version_id, type);


--
-- Name: notification_queue notification_queue_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_pkey PRIMARY KEY (id);


--
-- Name: notification_queue notification_queue_unique_constraint; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_unique_constraint UNIQUE (user_id, game_id, game_version_id, channel);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


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
-- Name: processed_events processed_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.processed_events
    ADD CONSTRAINT processed_events_pkey PRIMARY KEY (id);


--
-- Name: push_subscriptions push_subscriptions_endpoint_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_endpoint_unique UNIQUE (endpoint);


--
-- Name: push_subscriptions push_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: raters raters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.raters
    ADD CONSTRAINT raters_pkey PRIMARY KEY (id);


--
-- Name: ratings ratings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_pkey PRIMARY KEY (id);


--
-- Name: ratings ratings_source_platform_external_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_source_platform_external_id_unique UNIQUE (source_platform, external_id);


--
-- Name: ratings ratings_user_id_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_user_id_game_id_unique UNIQUE (user_id, game_id);


--
-- Name: review_reports review_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.review_reports
    ADD CONSTRAINT review_reports_pkey PRIMARY KEY (id);


--
-- Name: review_reports review_reports_rating_id_reporter_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.review_reports
    ADD CONSTRAINT review_reports_rating_id_reporter_id_unique UNIQUE (rating_id, reporter_id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: social_accounts social_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_accounts
    ADD CONSTRAINT social_accounts_pkey PRIMARY KEY (id);


--
-- Name: social_accounts social_accounts_provider_name_provider_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_accounts
    ADD CONSTRAINT social_accounts_provider_name_provider_id_unique UNIQUE (provider_name, provider_id);


--
-- Name: tags tags_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_name_unique UNIQUE (name);


--
-- Name: tags tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- Name: tags tags_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_slug_unique UNIQUE (slug);


--
-- Name: unique_dialogue_texts unique_dialogue_texts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unique_dialogue_texts
    ADD CONSTRAINT unique_dialogue_texts_pkey PRIMARY KEY (id);


--
-- Name: unique_dialogue_texts unique_dialogue_texts_text_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.unique_dialogue_texts
    ADD CONSTRAINT unique_dialogue_texts_text_hash_unique UNIQUE (text_hash);


--
-- Name: user_game_progress user_game_progress_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_pkey PRIMARY KEY (id);


--
-- Name: user_game_progress user_game_progress_user_id_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_user_id_game_id_unique UNIQUE (user_id, game_id);


--
-- Name: user_ignored_games user_ignored_games_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ignored_games
    ADD CONSTRAINT user_ignored_games_pkey PRIMARY KEY (id);


--
-- Name: user_ignored_games user_ignored_games_user_id_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ignored_games
    ADD CONSTRAINT user_ignored_games_user_id_game_id_unique UNIQUE (user_id, game_id);


--
-- Name: user_notification_preferences user_notification_preferences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_preferences
    ADD CONSTRAINT user_notification_preferences_pkey PRIMARY KEY (id);


--
-- Name: user_notification_preferences user_notification_preferences_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_preferences
    ADD CONSTRAINT user_notification_preferences_user_id_unique UNIQUE (user_id);


--
-- Name: user_preferences user_preferences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_preferences
    ADD CONSTRAINT user_preferences_pkey PRIMARY KEY (id);


--
-- Name: user_preferences user_preferences_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_preferences
    ADD CONSTRAINT user_preferences_user_id_unique UNIQUE (user_id);


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
-- Name: version_dialogue_lines version_dialogue_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_dialogue_lines
    ADD CONSTRAINT version_dialogue_lines_pkey PRIMARY KEY (id);


--
-- Name: version_file_categories version_file_categories_game_version_id_category_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_categories
    ADD CONSTRAINT version_file_categories_game_version_id_category_unique UNIQUE (game_version_id, category);


--
-- Name: version_file_categories version_file_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_categories
    ADD CONSTRAINT version_file_categories_pkey PRIMARY KEY (id);


--
-- Name: version_file_types version_file_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_types
    ADD CONSTRAINT version_file_types_pkey PRIMARY KEY (id);


--
-- Name: version_file_types version_file_types_version_file_category_id_extension_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_types
    ADD CONSTRAINT version_file_types_version_file_category_id_extension_unique UNIQUE (version_file_category_id, extension);


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
-- Name: version_route_edges version_route_edges_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_edges
    ADD CONSTRAINT version_route_edges_pkey PRIMARY KEY (id);


--
-- Name: version_route_labels version_route_labels_game_version_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_labels
    ADD CONSTRAINT version_route_labels_game_version_id_name_unique UNIQUE (game_version_id, name);


--
-- Name: version_route_labels version_route_labels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_labels
    ADD CONSTRAINT version_route_labels_pkey PRIMARY KEY (id);


--
-- Name: version_route_menu_choices version_route_menu_choices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_menu_choices
    ADD CONSTRAINT version_route_menu_choices_pkey PRIMARY KEY (id);


--
-- Name: version_route_paths version_route_paths_game_version_id_ending_label_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_paths
    ADD CONSTRAINT version_route_paths_game_version_id_ending_label_unique UNIQUE (game_version_id, ending_label);


--
-- Name: version_route_paths version_route_paths_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_paths
    ADD CONSTRAINT version_route_paths_pkey PRIMARY KEY (id);


--
-- Name: version_route_variable_changes version_route_variable_changes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_variable_changes
    ADD CONSTRAINT version_route_variable_changes_pkey PRIMARY KEY (id);


--
-- Name: version_route_variables version_route_variables_game_version_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_variables
    ADD CONSTRAINT version_route_variables_game_version_id_name_unique UNIQUE (game_version_id, name);


--
-- Name: version_route_variables version_route_variables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_variables
    ADD CONSTRAINT version_route_variables_pkey PRIMARY KEY (id);


--
-- Name: version_supported_languages version_supported_languages_game_version_id_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages
    ADD CONSTRAINT version_supported_languages_game_version_id_iso_code_unique UNIQUE (game_version_id, iso_code);


--
-- Name: version_supported_languages version_supported_languages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages
    ADD CONSTRAINT version_supported_languages_pkey PRIMARY KEY (id);


--
-- Name: version_word_frequencies version_word_frequencies_game_version_id_iso_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_word_frequencies
    ADD CONSTRAINT version_word_frequencies_game_version_id_iso_code_unique UNIQUE (game_version_id, iso_code);


--
-- Name: version_word_frequencies version_word_frequencies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_word_frequencies
    ADD CONSTRAINT version_word_frequencies_pkey PRIMARY KEY (id);


--
-- Name: vn_list_entries vn_list_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries
    ADD CONSTRAINT vn_list_entries_pkey PRIMARY KEY (id);


--
-- Name: vn_list_entries vn_list_entries_vn_list_id_game_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries
    ADD CONSTRAINT vn_list_entries_vn_list_id_game_id_unique UNIQUE (vn_list_id, game_id);


--
-- Name: vn_lists vn_lists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_lists
    ADD CONSTRAINT vn_lists_pkey PRIMARY KEY (id);


--
-- Name: vn_lists vn_lists_user_id_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_lists
    ADD CONSTRAINT vn_lists_user_id_name_unique UNIQUE (user_id, name);


--
-- Name: addition_request_users_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX addition_request_users_user_id_created_at_index ON public.addition_request_users USING btree (user_id, created_at);


--
-- Name: addition_requests_itch_url_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX addition_requests_itch_url_index ON public.addition_requests USING btree (game_url);


--
-- Name: addition_requests_normalized_url_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX addition_requests_normalized_url_index ON public.addition_requests USING btree (normalized_url);


--
-- Name: addition_requests_normalized_url_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX addition_requests_normalized_url_status_index ON public.addition_requests USING btree (normalized_url, status);


--
-- Name: addition_requests_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX addition_requests_status_created_at_index ON public.addition_requests USING btree (status, created_at);


--
-- Name: addition_requests_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX addition_requests_status_index ON public.addition_requests USING btree (status);


--
-- Name: android_builds_game_id_game_version_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX android_builds_game_id_game_version_id_status_index ON public.android_builds USING btree (game_id, game_version_id, status);


--
-- Name: android_builds_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX android_builds_user_id_status_index ON public.android_builds USING btree (user_id, status);


--
-- Name: bug_report_comments_bug_report_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bug_report_comments_bug_report_id_created_at_index ON public.bug_report_comments USING btree (bug_report_id, created_at);


--
-- Name: bug_report_comments_bug_report_id_is_read_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bug_report_comments_bug_report_id_is_read_index ON public.bug_report_comments USING btree (bug_report_id, is_read);


--
-- Name: bug_reports_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bug_reports_created_at_index ON public.bug_reports USING btree (created_at);


--
-- Name: bug_reports_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bug_reports_status_index ON public.bug_reports USING btree (status);


--
-- Name: bug_reports_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bug_reports_user_id_index ON public.bug_reports USING btree (user_id);


--
-- Name: idx_change_logs_entity; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_entity ON ONLY public.change_logs USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m01_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m01 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: idx_change_logs_event; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_event ON ONLY public.change_logs USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m01_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_event_type_timestamp_idx ON public.change_logs_y2025m01 USING btree (event_type, "timestamp");


--
-- Name: idx_change_logs_request_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_request_id ON ONLY public.change_logs USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m01_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_expr_idx ON public.change_logs_y2025m01 USING gin (((context -> 'request_id'::text)));


--
-- Name: idx_change_logs_session_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_session_id ON ONLY public.change_logs USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m01_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_expr_idx1 ON public.change_logs_y2025m01 USING gin (((context -> 'session_id'::text)));


--
-- Name: idx_change_logs_command_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_command_name ON ONLY public.change_logs USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m01_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_expr_idx2 ON public.change_logs_y2025m01 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: idx_change_logs_request_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_request_time ON ONLY public.change_logs USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m01_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_expr_timestamp_idx ON public.change_logs_y2025m01 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: idx_change_logs_session_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_session_time ON ONLY public.change_logs USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m01_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_expr_timestamp_idx1 ON public.change_logs_y2025m01 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: idx_change_logs_command_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_command_time ON ONLY public.change_logs USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m01_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_expr_timestamp_idx2 ON public.change_logs_y2025m01 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: idx_change_logs_timestamp; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_timestamp ON ONLY public.change_logs USING btree ("timestamp");


--
-- Name: change_logs_y2025m01_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_timestamp_idx ON public.change_logs_y2025m01 USING btree ("timestamp");


--
-- Name: idx_change_logs_user; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_change_logs_user ON ONLY public.change_logs USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m01_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m01_user_id_timestamp_idx ON public.change_logs_y2025m01 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m02_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m02 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m02_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_event_type_timestamp_idx ON public.change_logs_y2025m02 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m02_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_expr_idx ON public.change_logs_y2025m02 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m02_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_expr_idx1 ON public.change_logs_y2025m02 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m02_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_expr_idx2 ON public.change_logs_y2025m02 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m02_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_expr_timestamp_idx ON public.change_logs_y2025m02 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m02_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_expr_timestamp_idx1 ON public.change_logs_y2025m02 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m02_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_expr_timestamp_idx2 ON public.change_logs_y2025m02 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m02_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_timestamp_idx ON public.change_logs_y2025m02 USING btree ("timestamp");


--
-- Name: change_logs_y2025m02_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m02_user_id_timestamp_idx ON public.change_logs_y2025m02 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m03_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m03 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m03_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_event_type_timestamp_idx ON public.change_logs_y2025m03 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m03_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_expr_idx ON public.change_logs_y2025m03 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m03_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_expr_idx1 ON public.change_logs_y2025m03 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m03_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_expr_idx2 ON public.change_logs_y2025m03 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m03_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_expr_timestamp_idx ON public.change_logs_y2025m03 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m03_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_expr_timestamp_idx1 ON public.change_logs_y2025m03 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m03_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_expr_timestamp_idx2 ON public.change_logs_y2025m03 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m03_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_timestamp_idx ON public.change_logs_y2025m03 USING btree ("timestamp");


--
-- Name: change_logs_y2025m03_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m03_user_id_timestamp_idx ON public.change_logs_y2025m03 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m04_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m04 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m04_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_event_type_timestamp_idx ON public.change_logs_y2025m04 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m04_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_expr_idx ON public.change_logs_y2025m04 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m04_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_expr_idx1 ON public.change_logs_y2025m04 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m04_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_expr_idx2 ON public.change_logs_y2025m04 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m04_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_expr_timestamp_idx ON public.change_logs_y2025m04 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m04_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_expr_timestamp_idx1 ON public.change_logs_y2025m04 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m04_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_expr_timestamp_idx2 ON public.change_logs_y2025m04 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m04_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_timestamp_idx ON public.change_logs_y2025m04 USING btree ("timestamp");


--
-- Name: change_logs_y2025m04_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m04_user_id_timestamp_idx ON public.change_logs_y2025m04 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m05_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m05 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m05_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_event_type_timestamp_idx ON public.change_logs_y2025m05 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m05_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_expr_idx ON public.change_logs_y2025m05 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m05_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_expr_idx1 ON public.change_logs_y2025m05 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m05_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_expr_idx2 ON public.change_logs_y2025m05 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m05_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_expr_timestamp_idx ON public.change_logs_y2025m05 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m05_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_expr_timestamp_idx1 ON public.change_logs_y2025m05 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m05_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_expr_timestamp_idx2 ON public.change_logs_y2025m05 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m05_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_timestamp_idx ON public.change_logs_y2025m05 USING btree ("timestamp");


--
-- Name: change_logs_y2025m05_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m05_user_id_timestamp_idx ON public.change_logs_y2025m05 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m06_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m06 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m06_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_event_type_timestamp_idx ON public.change_logs_y2025m06 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m06_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_expr_idx ON public.change_logs_y2025m06 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m06_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_expr_idx1 ON public.change_logs_y2025m06 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m06_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_expr_idx2 ON public.change_logs_y2025m06 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m06_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_expr_timestamp_idx ON public.change_logs_y2025m06 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m06_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_expr_timestamp_idx1 ON public.change_logs_y2025m06 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m06_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_expr_timestamp_idx2 ON public.change_logs_y2025m06 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m06_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_timestamp_idx ON public.change_logs_y2025m06 USING btree ("timestamp");


--
-- Name: change_logs_y2025m06_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m06_user_id_timestamp_idx ON public.change_logs_y2025m06 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m07_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m07 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m07_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_event_type_timestamp_idx ON public.change_logs_y2025m07 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m07_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_expr_idx ON public.change_logs_y2025m07 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m07_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_expr_idx1 ON public.change_logs_y2025m07 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m07_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_expr_idx2 ON public.change_logs_y2025m07 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m07_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_expr_timestamp_idx ON public.change_logs_y2025m07 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m07_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_expr_timestamp_idx1 ON public.change_logs_y2025m07 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m07_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_expr_timestamp_idx2 ON public.change_logs_y2025m07 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m07_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_timestamp_idx ON public.change_logs_y2025m07 USING btree ("timestamp");


--
-- Name: change_logs_y2025m07_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m07_user_id_timestamp_idx ON public.change_logs_y2025m07 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m08_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m08 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m08_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_event_type_timestamp_idx ON public.change_logs_y2025m08 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m08_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_expr_idx ON public.change_logs_y2025m08 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m08_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_expr_idx1 ON public.change_logs_y2025m08 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m08_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_expr_idx2 ON public.change_logs_y2025m08 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m08_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_expr_timestamp_idx ON public.change_logs_y2025m08 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m08_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_expr_timestamp_idx1 ON public.change_logs_y2025m08 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m08_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_expr_timestamp_idx2 ON public.change_logs_y2025m08 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m08_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_timestamp_idx ON public.change_logs_y2025m08 USING btree ("timestamp");


--
-- Name: change_logs_y2025m08_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m08_user_id_timestamp_idx ON public.change_logs_y2025m08 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m09_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m09 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m09_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_event_type_timestamp_idx ON public.change_logs_y2025m09 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m09_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_expr_idx ON public.change_logs_y2025m09 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m09_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_expr_idx1 ON public.change_logs_y2025m09 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m09_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_expr_idx2 ON public.change_logs_y2025m09 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m09_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_expr_timestamp_idx ON public.change_logs_y2025m09 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m09_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_expr_timestamp_idx1 ON public.change_logs_y2025m09 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m09_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_expr_timestamp_idx2 ON public.change_logs_y2025m09 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m09_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_timestamp_idx ON public.change_logs_y2025m09 USING btree ("timestamp");


--
-- Name: change_logs_y2025m09_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m09_user_id_timestamp_idx ON public.change_logs_y2025m09 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m10_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m10 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m10_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_event_type_timestamp_idx ON public.change_logs_y2025m10 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m10_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_expr_idx ON public.change_logs_y2025m10 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m10_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_expr_idx1 ON public.change_logs_y2025m10 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m10_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_expr_idx2 ON public.change_logs_y2025m10 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m10_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_expr_timestamp_idx ON public.change_logs_y2025m10 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m10_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_expr_timestamp_idx1 ON public.change_logs_y2025m10 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m10_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_expr_timestamp_idx2 ON public.change_logs_y2025m10 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m10_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_timestamp_idx ON public.change_logs_y2025m10 USING btree ("timestamp");


--
-- Name: change_logs_y2025m10_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m10_user_id_timestamp_idx ON public.change_logs_y2025m10 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m11_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m11 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m11_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_event_type_timestamp_idx ON public.change_logs_y2025m11 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m11_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_expr_idx ON public.change_logs_y2025m11 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m11_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_expr_idx1 ON public.change_logs_y2025m11 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m11_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_expr_idx2 ON public.change_logs_y2025m11 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m11_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_expr_timestamp_idx ON public.change_logs_y2025m11 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m11_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_expr_timestamp_idx1 ON public.change_logs_y2025m11 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m11_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_expr_timestamp_idx2 ON public.change_logs_y2025m11 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m11_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_timestamp_idx ON public.change_logs_y2025m11 USING btree ("timestamp");


--
-- Name: change_logs_y2025m11_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m11_user_id_timestamp_idx ON public.change_logs_y2025m11 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2025m12_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_entity_type_entity_id_timestamp_idx ON public.change_logs_y2025m12 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2025m12_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_event_type_timestamp_idx ON public.change_logs_y2025m12 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2025m12_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_expr_idx ON public.change_logs_y2025m12 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2025m12_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_expr_idx1 ON public.change_logs_y2025m12 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2025m12_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_expr_idx2 ON public.change_logs_y2025m12 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2025m12_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_expr_timestamp_idx ON public.change_logs_y2025m12 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m12_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_expr_timestamp_idx1 ON public.change_logs_y2025m12 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2025m12_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_expr_timestamp_idx2 ON public.change_logs_y2025m12 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2025m12_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_timestamp_idx ON public.change_logs_y2025m12 USING btree ("timestamp");


--
-- Name: change_logs_y2025m12_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2025m12_user_id_timestamp_idx ON public.change_logs_y2025m12 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m01_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m01 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m01_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_event_type_timestamp_idx ON public.change_logs_y2026m01 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m01_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_expr_idx ON public.change_logs_y2026m01 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m01_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_expr_idx1 ON public.change_logs_y2026m01 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m01_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_expr_idx2 ON public.change_logs_y2026m01 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m01_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_expr_timestamp_idx ON public.change_logs_y2026m01 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m01_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_expr_timestamp_idx1 ON public.change_logs_y2026m01 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m01_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_expr_timestamp_idx2 ON public.change_logs_y2026m01 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m01_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_timestamp_idx ON public.change_logs_y2026m01 USING btree ("timestamp");


--
-- Name: change_logs_y2026m01_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m01_user_id_timestamp_idx ON public.change_logs_y2026m01 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m02_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m02 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m02_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_event_type_timestamp_idx ON public.change_logs_y2026m02 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m02_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_expr_idx ON public.change_logs_y2026m02 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m02_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_expr_idx1 ON public.change_logs_y2026m02 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m02_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_expr_idx2 ON public.change_logs_y2026m02 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m02_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_expr_timestamp_idx ON public.change_logs_y2026m02 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m02_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_expr_timestamp_idx1 ON public.change_logs_y2026m02 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m02_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_expr_timestamp_idx2 ON public.change_logs_y2026m02 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m02_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_timestamp_idx ON public.change_logs_y2026m02 USING btree ("timestamp");


--
-- Name: change_logs_y2026m02_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m02_user_id_timestamp_idx ON public.change_logs_y2026m02 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m03_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m03 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m03_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_event_type_timestamp_idx ON public.change_logs_y2026m03 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m03_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_expr_idx ON public.change_logs_y2026m03 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m03_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_expr_idx1 ON public.change_logs_y2026m03 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m03_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_expr_idx2 ON public.change_logs_y2026m03 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m03_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_expr_timestamp_idx ON public.change_logs_y2026m03 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m03_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_expr_timestamp_idx1 ON public.change_logs_y2026m03 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m03_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_expr_timestamp_idx2 ON public.change_logs_y2026m03 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m03_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_timestamp_idx ON public.change_logs_y2026m03 USING btree ("timestamp");


--
-- Name: change_logs_y2026m03_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m03_user_id_timestamp_idx ON public.change_logs_y2026m03 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m04_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m04 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m04_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_event_type_timestamp_idx ON public.change_logs_y2026m04 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m04_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_expr_idx ON public.change_logs_y2026m04 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m04_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_expr_idx1 ON public.change_logs_y2026m04 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m04_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_expr_idx2 ON public.change_logs_y2026m04 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m04_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_expr_timestamp_idx ON public.change_logs_y2026m04 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m04_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_expr_timestamp_idx1 ON public.change_logs_y2026m04 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m04_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_expr_timestamp_idx2 ON public.change_logs_y2026m04 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m04_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_timestamp_idx ON public.change_logs_y2026m04 USING btree ("timestamp");


--
-- Name: change_logs_y2026m04_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m04_user_id_timestamp_idx ON public.change_logs_y2026m04 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m05_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m05 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m05_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_event_type_timestamp_idx ON public.change_logs_y2026m05 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m05_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_expr_idx ON public.change_logs_y2026m05 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m05_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_expr_idx1 ON public.change_logs_y2026m05 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m05_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_expr_idx2 ON public.change_logs_y2026m05 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m05_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_expr_timestamp_idx ON public.change_logs_y2026m05 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m05_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_expr_timestamp_idx1 ON public.change_logs_y2026m05 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m05_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_expr_timestamp_idx2 ON public.change_logs_y2026m05 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m05_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_timestamp_idx ON public.change_logs_y2026m05 USING btree ("timestamp");


--
-- Name: change_logs_y2026m05_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m05_user_id_timestamp_idx ON public.change_logs_y2026m05 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m06_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m06 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m06_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_event_type_timestamp_idx ON public.change_logs_y2026m06 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m06_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_expr_idx ON public.change_logs_y2026m06 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m06_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_expr_idx1 ON public.change_logs_y2026m06 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m06_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_expr_idx2 ON public.change_logs_y2026m06 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m06_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_expr_timestamp_idx ON public.change_logs_y2026m06 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m06_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_expr_timestamp_idx1 ON public.change_logs_y2026m06 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m06_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_expr_timestamp_idx2 ON public.change_logs_y2026m06 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m06_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_timestamp_idx ON public.change_logs_y2026m06 USING btree ("timestamp");


--
-- Name: change_logs_y2026m06_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m06_user_id_timestamp_idx ON public.change_logs_y2026m06 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m07_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m07 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m07_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_event_type_timestamp_idx ON public.change_logs_y2026m07 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m07_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_expr_idx ON public.change_logs_y2026m07 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m07_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_expr_idx1 ON public.change_logs_y2026m07 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m07_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_expr_idx2 ON public.change_logs_y2026m07 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m07_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_expr_timestamp_idx ON public.change_logs_y2026m07 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m07_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_expr_timestamp_idx1 ON public.change_logs_y2026m07 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m07_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_expr_timestamp_idx2 ON public.change_logs_y2026m07 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m07_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_timestamp_idx ON public.change_logs_y2026m07 USING btree ("timestamp");


--
-- Name: change_logs_y2026m07_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m07_user_id_timestamp_idx ON public.change_logs_y2026m07 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m08_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m08 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m08_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_event_type_timestamp_idx ON public.change_logs_y2026m08 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m08_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_expr_idx ON public.change_logs_y2026m08 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m08_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_expr_idx1 ON public.change_logs_y2026m08 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m08_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_expr_idx2 ON public.change_logs_y2026m08 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m08_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_expr_timestamp_idx ON public.change_logs_y2026m08 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m08_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_expr_timestamp_idx1 ON public.change_logs_y2026m08 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m08_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_expr_timestamp_idx2 ON public.change_logs_y2026m08 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m08_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_timestamp_idx ON public.change_logs_y2026m08 USING btree ("timestamp");


--
-- Name: change_logs_y2026m08_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m08_user_id_timestamp_idx ON public.change_logs_y2026m08 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m09_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m09 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m09_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_event_type_timestamp_idx ON public.change_logs_y2026m09 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m09_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_expr_idx ON public.change_logs_y2026m09 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m09_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_expr_idx1 ON public.change_logs_y2026m09 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m09_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_expr_idx2 ON public.change_logs_y2026m09 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m09_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_expr_timestamp_idx ON public.change_logs_y2026m09 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m09_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_expr_timestamp_idx1 ON public.change_logs_y2026m09 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m09_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_expr_timestamp_idx2 ON public.change_logs_y2026m09 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m09_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_timestamp_idx ON public.change_logs_y2026m09 USING btree ("timestamp");


--
-- Name: change_logs_y2026m09_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m09_user_id_timestamp_idx ON public.change_logs_y2026m09 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m10_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m10 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m10_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_event_type_timestamp_idx ON public.change_logs_y2026m10 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m10_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_expr_idx ON public.change_logs_y2026m10 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m10_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_expr_idx1 ON public.change_logs_y2026m10 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m10_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_expr_idx2 ON public.change_logs_y2026m10 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m10_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_expr_timestamp_idx ON public.change_logs_y2026m10 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m10_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_expr_timestamp_idx1 ON public.change_logs_y2026m10 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m10_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_expr_timestamp_idx2 ON public.change_logs_y2026m10 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m10_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_timestamp_idx ON public.change_logs_y2026m10 USING btree ("timestamp");


--
-- Name: change_logs_y2026m10_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m10_user_id_timestamp_idx ON public.change_logs_y2026m10 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m11_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m11 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m11_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_event_type_timestamp_idx ON public.change_logs_y2026m11 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m11_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_expr_idx ON public.change_logs_y2026m11 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m11_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_expr_idx1 ON public.change_logs_y2026m11 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m11_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_expr_idx2 ON public.change_logs_y2026m11 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m11_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_expr_timestamp_idx ON public.change_logs_y2026m11 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m11_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_expr_timestamp_idx1 ON public.change_logs_y2026m11 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m11_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_expr_timestamp_idx2 ON public.change_logs_y2026m11 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m11_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_timestamp_idx ON public.change_logs_y2026m11 USING btree ("timestamp");


--
-- Name: change_logs_y2026m11_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m11_user_id_timestamp_idx ON public.change_logs_y2026m11 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2026m12_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_entity_type_entity_id_timestamp_idx ON public.change_logs_y2026m12 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2026m12_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_event_type_timestamp_idx ON public.change_logs_y2026m12 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2026m12_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_expr_idx ON public.change_logs_y2026m12 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2026m12_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_expr_idx1 ON public.change_logs_y2026m12 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2026m12_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_expr_idx2 ON public.change_logs_y2026m12 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2026m12_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_expr_timestamp_idx ON public.change_logs_y2026m12 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m12_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_expr_timestamp_idx1 ON public.change_logs_y2026m12 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2026m12_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_expr_timestamp_idx2 ON public.change_logs_y2026m12 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2026m12_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_timestamp_idx ON public.change_logs_y2026m12 USING btree ("timestamp");


--
-- Name: change_logs_y2026m12_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2026m12_user_id_timestamp_idx ON public.change_logs_y2026m12 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m01_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m01 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m01_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_event_type_timestamp_idx ON public.change_logs_y2027m01 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m01_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_expr_idx ON public.change_logs_y2027m01 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m01_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_expr_idx1 ON public.change_logs_y2027m01 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m01_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_expr_idx2 ON public.change_logs_y2027m01 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m01_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_expr_timestamp_idx ON public.change_logs_y2027m01 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m01_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_expr_timestamp_idx1 ON public.change_logs_y2027m01 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m01_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_expr_timestamp_idx2 ON public.change_logs_y2027m01 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m01_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_timestamp_idx ON public.change_logs_y2027m01 USING btree ("timestamp");


--
-- Name: change_logs_y2027m01_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m01_user_id_timestamp_idx ON public.change_logs_y2027m01 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m02_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m02 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m02_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_event_type_timestamp_idx ON public.change_logs_y2027m02 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m02_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_expr_idx ON public.change_logs_y2027m02 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m02_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_expr_idx1 ON public.change_logs_y2027m02 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m02_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_expr_idx2 ON public.change_logs_y2027m02 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m02_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_expr_timestamp_idx ON public.change_logs_y2027m02 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m02_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_expr_timestamp_idx1 ON public.change_logs_y2027m02 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m02_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_expr_timestamp_idx2 ON public.change_logs_y2027m02 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m02_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_timestamp_idx ON public.change_logs_y2027m02 USING btree ("timestamp");


--
-- Name: change_logs_y2027m02_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m02_user_id_timestamp_idx ON public.change_logs_y2027m02 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m03_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m03 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m03_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_event_type_timestamp_idx ON public.change_logs_y2027m03 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m03_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_expr_idx ON public.change_logs_y2027m03 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m03_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_expr_idx1 ON public.change_logs_y2027m03 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m03_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_expr_idx2 ON public.change_logs_y2027m03 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m03_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_expr_timestamp_idx ON public.change_logs_y2027m03 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m03_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_expr_timestamp_idx1 ON public.change_logs_y2027m03 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m03_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_expr_timestamp_idx2 ON public.change_logs_y2027m03 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m03_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_timestamp_idx ON public.change_logs_y2027m03 USING btree ("timestamp");


--
-- Name: change_logs_y2027m03_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m03_user_id_timestamp_idx ON public.change_logs_y2027m03 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m04_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m04 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m04_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_event_type_timestamp_idx ON public.change_logs_y2027m04 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m04_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_expr_idx ON public.change_logs_y2027m04 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m04_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_expr_idx1 ON public.change_logs_y2027m04 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m04_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_expr_idx2 ON public.change_logs_y2027m04 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m04_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_expr_timestamp_idx ON public.change_logs_y2027m04 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m04_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_expr_timestamp_idx1 ON public.change_logs_y2027m04 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m04_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_expr_timestamp_idx2 ON public.change_logs_y2027m04 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m04_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_timestamp_idx ON public.change_logs_y2027m04 USING btree ("timestamp");


--
-- Name: change_logs_y2027m04_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m04_user_id_timestamp_idx ON public.change_logs_y2027m04 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m05_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m05 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m05_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_event_type_timestamp_idx ON public.change_logs_y2027m05 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m05_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_expr_idx ON public.change_logs_y2027m05 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m05_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_expr_idx1 ON public.change_logs_y2027m05 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m05_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_expr_idx2 ON public.change_logs_y2027m05 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m05_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_expr_timestamp_idx ON public.change_logs_y2027m05 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m05_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_expr_timestamp_idx1 ON public.change_logs_y2027m05 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m05_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_expr_timestamp_idx2 ON public.change_logs_y2027m05 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m05_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_timestamp_idx ON public.change_logs_y2027m05 USING btree ("timestamp");


--
-- Name: change_logs_y2027m05_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m05_user_id_timestamp_idx ON public.change_logs_y2027m05 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m06_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m06 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m06_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_event_type_timestamp_idx ON public.change_logs_y2027m06 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m06_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_expr_idx ON public.change_logs_y2027m06 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m06_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_expr_idx1 ON public.change_logs_y2027m06 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m06_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_expr_idx2 ON public.change_logs_y2027m06 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m06_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_expr_timestamp_idx ON public.change_logs_y2027m06 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m06_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_expr_timestamp_idx1 ON public.change_logs_y2027m06 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m06_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_expr_timestamp_idx2 ON public.change_logs_y2027m06 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m06_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_timestamp_idx ON public.change_logs_y2027m06 USING btree ("timestamp");


--
-- Name: change_logs_y2027m06_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m06_user_id_timestamp_idx ON public.change_logs_y2027m06 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m07_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m07 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m07_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_event_type_timestamp_idx ON public.change_logs_y2027m07 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m07_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_expr_idx ON public.change_logs_y2027m07 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m07_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_expr_idx1 ON public.change_logs_y2027m07 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m07_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_expr_idx2 ON public.change_logs_y2027m07 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m07_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_expr_timestamp_idx ON public.change_logs_y2027m07 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m07_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_expr_timestamp_idx1 ON public.change_logs_y2027m07 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m07_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_expr_timestamp_idx2 ON public.change_logs_y2027m07 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m07_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_timestamp_idx ON public.change_logs_y2027m07 USING btree ("timestamp");


--
-- Name: change_logs_y2027m07_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m07_user_id_timestamp_idx ON public.change_logs_y2027m07 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m08_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m08 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m08_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_event_type_timestamp_idx ON public.change_logs_y2027m08 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m08_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_expr_idx ON public.change_logs_y2027m08 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m08_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_expr_idx1 ON public.change_logs_y2027m08 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m08_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_expr_idx2 ON public.change_logs_y2027m08 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m08_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_expr_timestamp_idx ON public.change_logs_y2027m08 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m08_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_expr_timestamp_idx1 ON public.change_logs_y2027m08 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m08_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_expr_timestamp_idx2 ON public.change_logs_y2027m08 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m08_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_timestamp_idx ON public.change_logs_y2027m08 USING btree ("timestamp");


--
-- Name: change_logs_y2027m08_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m08_user_id_timestamp_idx ON public.change_logs_y2027m08 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m09_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m09 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m09_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_event_type_timestamp_idx ON public.change_logs_y2027m09 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m09_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_expr_idx ON public.change_logs_y2027m09 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m09_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_expr_idx1 ON public.change_logs_y2027m09 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m09_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_expr_idx2 ON public.change_logs_y2027m09 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m09_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_expr_timestamp_idx ON public.change_logs_y2027m09 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m09_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_expr_timestamp_idx1 ON public.change_logs_y2027m09 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m09_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_expr_timestamp_idx2 ON public.change_logs_y2027m09 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m09_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_timestamp_idx ON public.change_logs_y2027m09 USING btree ("timestamp");


--
-- Name: change_logs_y2027m09_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m09_user_id_timestamp_idx ON public.change_logs_y2027m09 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m10_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m10 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m10_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_event_type_timestamp_idx ON public.change_logs_y2027m10 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m10_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_expr_idx ON public.change_logs_y2027m10 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m10_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_expr_idx1 ON public.change_logs_y2027m10 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m10_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_expr_idx2 ON public.change_logs_y2027m10 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m10_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_expr_timestamp_idx ON public.change_logs_y2027m10 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m10_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_expr_timestamp_idx1 ON public.change_logs_y2027m10 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m10_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_expr_timestamp_idx2 ON public.change_logs_y2027m10 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m10_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_timestamp_idx ON public.change_logs_y2027m10 USING btree ("timestamp");


--
-- Name: change_logs_y2027m10_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m10_user_id_timestamp_idx ON public.change_logs_y2027m10 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m11_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m11 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m11_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_event_type_timestamp_idx ON public.change_logs_y2027m11 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m11_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_expr_idx ON public.change_logs_y2027m11 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m11_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_expr_idx1 ON public.change_logs_y2027m11 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m11_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_expr_idx2 ON public.change_logs_y2027m11 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m11_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_expr_timestamp_idx ON public.change_logs_y2027m11 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m11_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_expr_timestamp_idx1 ON public.change_logs_y2027m11 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m11_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_expr_timestamp_idx2 ON public.change_logs_y2027m11 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m11_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_timestamp_idx ON public.change_logs_y2027m11 USING btree ("timestamp");


--
-- Name: change_logs_y2027m11_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m11_user_id_timestamp_idx ON public.change_logs_y2027m11 USING btree (user_id, "timestamp");


--
-- Name: change_logs_y2027m12_entity_type_entity_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_entity_type_entity_id_timestamp_idx ON public.change_logs_y2027m12 USING btree (entity_type, entity_id, "timestamp");


--
-- Name: change_logs_y2027m12_event_type_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_event_type_timestamp_idx ON public.change_logs_y2027m12 USING btree (event_type, "timestamp");


--
-- Name: change_logs_y2027m12_expr_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_expr_idx ON public.change_logs_y2027m12 USING gin (((context -> 'request_id'::text)));


--
-- Name: change_logs_y2027m12_expr_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_expr_idx1 ON public.change_logs_y2027m12 USING gin (((context -> 'session_id'::text)));


--
-- Name: change_logs_y2027m12_expr_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_expr_idx2 ON public.change_logs_y2027m12 USING gin ((((context -> 'command'::text) -> 'name'::text)));


--
-- Name: change_logs_y2027m12_expr_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_expr_timestamp_idx ON public.change_logs_y2027m12 USING btree (((context -> 'request_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m12_expr_timestamp_idx1; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_expr_timestamp_idx1 ON public.change_logs_y2027m12 USING btree (((context -> 'session_id'::text)), "timestamp");


--
-- Name: change_logs_y2027m12_expr_timestamp_idx2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_expr_timestamp_idx2 ON public.change_logs_y2027m12 USING btree ((((context -> 'command'::text) -> 'name'::text)), "timestamp");


--
-- Name: change_logs_y2027m12_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_timestamp_idx ON public.change_logs_y2027m12 USING btree ("timestamp");


--
-- Name: change_logs_y2027m12_user_id_timestamp_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX change_logs_y2027m12_user_id_timestamp_idx ON public.change_logs_y2027m12 USING btree (user_id, "timestamp");


--
-- Name: click_stats_clicked_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_clicked_at_index ON public.click_stats USING btree (clicked_at);


--
-- Name: click_stats_game_id_link_id_clicked_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_game_id_link_id_clicked_at_index ON public.click_stats USING btree (game_id, link_id, clicked_at);


--
-- Name: click_stats_game_id_type_clicked_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_game_id_type_clicked_at_index ON public.click_stats USING btree (game_id, type, clicked_at);


--
-- Name: click_stats_human_game_type_clicked_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_human_game_type_clicked_at_index ON public.click_stats USING btree (game_id, type, clicked_at) WHERE (bot_reason IS NULL);


--
-- Name: click_stats_human_type_clicked_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_human_type_clicked_at_index ON public.click_stats USING btree (type, clicked_at) WHERE (bot_reason IS NULL);


--
-- Name: click_stats_link_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_link_id_index ON public.click_stats USING btree (link_id);


--
-- Name: click_stats_session_id_game_id_type_link_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_session_id_game_id_type_link_id_index ON public.click_stats USING btree (session_id, game_id, type, link_id);


--
-- Name: click_stats_session_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_session_id_index ON public.click_stats USING btree (session_id);


--
-- Name: click_stats_type_clicked_at_game_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_type_clicked_at_game_id_index ON public.click_stats USING btree (type, clicked_at, game_id);


--
-- Name: click_stats_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_type_index ON public.click_stats USING btree (type);


--
-- Name: click_stats_user_id_clicked_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX click_stats_user_id_clicked_at_index ON public.click_stats USING btree (user_id, clicked_at);


--
-- Name: discord_channel_announcements_status_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_channel_announcements_status_updated_at_index ON public.discord_channel_announcements USING btree (status, updated_at);


--
-- Name: discord_notification_history_delivery_mode_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_notification_history_delivery_mode_index ON public.discord_notification_history USING btree (delivery_mode);


--
-- Name: discord_notification_history_delivery_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_notification_history_delivery_status_created_at_index ON public.discord_notification_history USING btree (delivery_status, created_at);


--
-- Name: discord_notification_history_delivery_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_notification_history_delivery_status_index ON public.discord_notification_history USING btree (delivery_status);


--
-- Name: discord_notification_history_discord_server_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_notification_history_discord_server_id_index ON public.discord_notification_history USING btree (discord_server_id);


--
-- Name: discord_notification_history_discord_server_id_sent_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_notification_history_discord_server_id_sent_at_index ON public.discord_notification_history USING btree (discord_server_id, sent_at);


--
-- Name: discord_notification_history_game_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_notification_history_game_id_index ON public.discord_notification_history USING btree (game_id);


--
-- Name: discord_notification_history_payload_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_notification_history_payload_hash_index ON public.discord_notification_history USING btree (payload_hash);


--
-- Name: discord_notification_history_sent_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_notification_history_sent_at_index ON public.discord_notification_history USING btree (sent_at);


--
-- Name: discord_server_configs_notification_channel_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_configs_notification_channel_id_index ON public.discord_server_configs USING btree (notification_channel_id);


--
-- Name: discord_server_games_discord_channel_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_games_discord_channel_id_index ON public.discord_server_games USING btree (discord_channel_id);


--
-- Name: discord_server_games_discord_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_games_discord_message_id_index ON public.discord_server_games USING btree (discord_message_id);


--
-- Name: discord_server_games_discord_payload_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_games_discord_payload_hash_index ON public.discord_server_games USING btree (discord_payload_hash);


--
-- Name: discord_server_games_discord_server_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_games_discord_server_id_index ON public.discord_server_games USING btree (discord_server_id);


--
-- Name: discord_server_games_game_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_games_game_id_index ON public.discord_server_games USING btree (game_id);


--
-- Name: discord_server_members_discord_server_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_members_discord_server_id_index ON public.discord_server_members USING btree (discord_server_id);


--
-- Name: discord_server_members_discord_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_members_discord_user_id_index ON public.discord_server_members USING btree (discord_user_id);


--
-- Name: discord_server_members_is_admin_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_members_is_admin_index ON public.discord_server_members USING btree (is_admin);


--
-- Name: discord_server_members_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_members_user_id_index ON public.discord_server_members USING btree (user_id);


--
-- Name: discord_server_tags_discord_server_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_tags_discord_server_id_index ON public.discord_server_tags USING btree (discord_server_id);


--
-- Name: discord_server_tags_is_subscribed_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_server_tags_is_subscribed_index ON public.discord_server_tags USING btree (is_subscribed);


--
-- Name: discord_servers_discord_server_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_servers_discord_server_id_index ON public.discord_servers USING btree (discord_server_id);


--
-- Name: discord_servers_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_servers_is_active_index ON public.discord_servers USING btree (is_active);


--
-- Name: discord_servers_owner_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX discord_servers_owner_user_id_index ON public.discord_servers USING btree (owner_user_id);


--
-- Name: game_discord_subscriptions_discord_server_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX game_discord_subscriptions_discord_server_id_index ON public.game_discord_subscriptions USING btree (discord_server_id);


--
-- Name: game_discord_subscriptions_game_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX game_discord_subscriptions_game_id_index ON public.game_discord_subscriptions USING btree (game_id);


--
-- Name: game_discord_subscriptions_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX game_discord_subscriptions_is_active_index ON public.game_discord_subscriptions USING btree (is_active);


--
-- Name: game_tag_tag_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX game_tag_tag_id_index ON public.game_tag USING btree (tag_id);


--
-- Name: game_versions_game_id_is_latest_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX game_versions_game_id_is_latest_index ON public.game_versions USING btree (game_id, is_latest);


--
-- Name: games_authors_fulltext; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_authors_fulltext ON public.games USING gin (to_tsvector('english'::regconfig, (authors)::text));


--
-- Name: games_content_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_content_type_index ON public.games USING btree (content_type);


--
-- Name: games_custom_tags_fulltext; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_custom_tags_fulltext ON public.games USING gin (to_tsvector('english'::regconfig, (custom_tags)::text));


--
-- Name: games_has_custom_page_is_visible_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_has_custom_page_is_visible_index ON public.games USING btree (has_custom_page, is_visible);


--
-- Name: games_is_visible_first_visible_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_is_visible_first_visible_at_index ON public.games USING btree (is_visible, first_visible_at);


--
-- Name: games_platform_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_platform_index ON public.games USING btree (platform);


--
-- Name: games_rating_score_rating_count_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_rating_score_rating_count_index ON public.games USING btree (rating_score, rating_count);


--
-- Name: games_steam_app_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_steam_app_id_index ON public.games USING btree (steam_app_id);


--
-- Name: games_view_mode_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_view_mode_index ON public.games USING btree (view_mode);


--
-- Name: games_visible_content_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_visible_content_type_index ON public.games USING btree (is_visible, content_type);


--
-- Name: games_visible_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_visible_index ON public.games USING btree (is_visible);


--
-- Name: games_visible_nsfw_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_visible_nsfw_index ON public.games USING btree (is_visible, is_nsfw);


--
-- Name: games_visible_trending_score_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX games_visible_trending_score_index ON public.games USING btree (is_visible, trending_score);


--
-- Name: idx_game_versions_latest_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_game_versions_latest_lookup ON public.game_versions USING btree (is_latest, game_id, id);


--
-- Name: idx_processed_events_event_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_processed_events_event_id ON public.processed_events USING btree (event_id);


--
-- Name: idx_ratings_game_visible; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_game_visible ON public.games USING btree (is_visible);


--
-- Name: idx_ratings_rater_visible_published_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_rater_visible_published_at ON public.ratings USING btree (rater_id, is_visible, published_at DESC);


--
-- Name: idx_ratings_rater_visible_rating; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_rater_visible_rating ON public.ratings USING btree (rater_id, is_visible, rating DESC);


--
-- Name: idx_ratings_rating_when_visible_reviewed; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_rating_when_visible_reviewed ON public.ratings USING btree (rating) WHERE ((is_visible = true) AND (is_reviewed = true));


--
-- Name: idx_ratings_user_visible_published_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_user_visible_published_at ON public.ratings USING btree (user_id, is_visible, published_at);


--
-- Name: idx_ratings_user_visible_rating; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_user_visible_rating ON public.ratings USING btree (user_id, is_visible, rating);


--
-- Name: idx_ratings_visible_only; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_visible_only ON public.ratings USING btree (rater_id, rating) WHERE (is_visible = true);


--
-- Name: idx_ratings_visible_published_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_visible_published_at ON public.ratings USING btree (is_visible, published_at DESC);


--
-- Name: idx_ratings_visible_rater; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_visible_rater ON public.ratings USING btree (is_visible, rater_id);


--
-- Name: idx_ratings_visible_rating; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_visible_rating ON public.ratings USING btree (is_visible, rating DESC);


--
-- Name: idx_ratings_visible_reviewed; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ratings_visible_reviewed ON public.ratings USING btree (is_visible, is_reviewed);


--
-- Name: idx_version_stats_version_lang; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_version_stats_version_lang ON public.version_language_stats USING btree (game_version_id, iso_code);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: notification_history_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_history_type_index ON public.notification_history USING btree (type);


--
-- Name: notification_history_user_id_game_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_history_user_id_game_id_type_index ON public.notification_history USING btree (user_id, game_id, type);


--
-- Name: notification_queue_channel_status_scheduled_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_queue_channel_status_scheduled_at_index ON public.notification_queue USING btree (channel, status, scheduled_at);


--
-- Name: notification_queue_processing_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_queue_processing_index ON public.notification_queue USING btree (status, scheduled_at);


--
-- Name: notification_queue_user_id_channel_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notification_queue_user_id_channel_status_index ON public.notification_queue USING btree (user_id, channel, status);


--
-- Name: notifications_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_created_at_index ON public.notifications USING btree (created_at);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: notifications_read_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_read_at_index ON public.notifications USING btree (read_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: push_subscriptions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX push_subscriptions_user_id_index ON public.push_subscriptions USING btree (user_id);


--
-- Name: raters_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX raters_name_index ON public.raters USING btree (name);


--
-- Name: raters_steam_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX raters_steam_id_index ON public.raters USING btree (steam_id);


--
-- Name: ratings_game_id_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_game_id_visible_has_review_index ON public.ratings USING btree (game_id, is_visible, is_reviewed);


--
-- Name: ratings_published_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_published_at_index ON public.ratings USING btree (published_at);


--
-- Name: ratings_rater_id_game_id_published_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_rater_id_game_id_published_at_index ON public.ratings USING btree (rater_id, game_id, published_at);


--
-- Name: ratings_rater_id_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_rater_id_visible_has_review_index ON public.ratings USING btree (rater_id, is_visible, is_reviewed);


--
-- Name: ratings_source_platform_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_source_platform_index ON public.ratings USING btree (source_platform);


--
-- Name: ratings_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_user_id_index ON public.ratings USING btree (user_id);


--
-- Name: ratings_visible_has_review_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ratings_visible_has_review_index ON public.ratings USING btree (is_visible, is_reviewed);


--
-- Name: review_reports_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX review_reports_status_index ON public.review_reports USING btree (status);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: social_accounts_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX social_accounts_user_id_index ON public.social_accounts USING btree (user_id);


--
-- Name: unique_dialogue_texts_search_vector_gin_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX unique_dialogue_texts_search_vector_gin_index ON public.unique_dialogue_texts USING gin (search_vector);


--
-- Name: user_game_progress_game_notifications_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_game_progress_game_notifications_index ON public.user_game_progress USING btree (game_id, receive_updates);


--
-- Name: user_game_progress_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_game_progress_user_id_index ON public.user_game_progress USING btree (user_id);


--
-- Name: user_ignored_games_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_ignored_games_user_id_created_at_index ON public.user_ignored_games USING btree (user_id, created_at);


--
-- Name: version_dialogue_lines_character_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_dialogue_lines_character_id_index ON public.version_dialogue_lines USING btree (character_id);


--
-- Name: version_dialogue_lines_game_version_id_iso_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_dialogue_lines_game_version_id_iso_code_index ON public.version_dialogue_lines USING btree (game_version_id, iso_code);


--
-- Name: version_file_categories_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_file_categories_category_index ON public.version_file_categories USING btree (category);


--
-- Name: version_file_types_extension_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_file_types_extension_index ON public.version_file_types USING btree (extension);


--
-- Name: version_route_edges_edge_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_edges_edge_type_index ON public.version_route_edges USING btree (edge_type);


--
-- Name: version_route_edges_game_version_id_from_label_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_edges_game_version_id_from_label_index ON public.version_route_edges USING btree (game_version_id, from_label);


--
-- Name: version_route_edges_game_version_id_to_label_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_edges_game_version_id_to_label_index ON public.version_route_edges USING btree (game_version_id, to_label);


--
-- Name: version_route_labels_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_labels_name_index ON public.version_route_labels USING btree (name);


--
-- Name: version_route_menu_choices_game_version_id_from_label_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_menu_choices_game_version_id_from_label_index ON public.version_route_menu_choices USING btree (game_version_id, from_label);


--
-- Name: version_route_menu_choices_game_version_id_from_label_menu_line; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_menu_choices_game_version_id_from_label_menu_line ON public.version_route_menu_choices USING btree (game_version_id, from_label, menu_line);


--
-- Name: version_route_menu_choices_game_version_id_target_label_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_menu_choices_game_version_id_target_label_index ON public.version_route_menu_choices USING btree (game_version_id, target_label);


--
-- Name: version_route_paths_game_version_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_paths_game_version_id_index ON public.version_route_paths USING btree (game_version_id);


--
-- Name: version_route_variable_changes_game_version_id_label_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_variable_changes_game_version_id_label_index ON public.version_route_variable_changes USING btree (game_version_id, label);


--
-- Name: version_route_variable_changes_game_version_id_variable_name_in; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_variable_changes_game_version_id_variable_name_in ON public.version_route_variable_changes USING btree (game_version_id, variable_name);


--
-- Name: version_route_variables_game_version_id_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_route_variables_game_version_id_type_index ON public.version_route_variables USING btree (game_version_id, type);


--
-- Name: version_word_frequencies_calculated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX version_word_frequencies_calculated_at_index ON public.version_word_frequencies USING btree (calculated_at);


--
-- Name: vn_list_entries_game_list_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vn_list_entries_game_list_index ON public.vn_list_entries USING btree (game_id, vn_list_id);


--
-- Name: vn_list_entries_list_sort_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vn_list_entries_list_sort_index ON public.vn_list_entries USING btree (vn_list_id, sort_order);


--
-- Name: vn_list_entries_max_sort_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vn_list_entries_max_sort_index ON public.vn_list_entries USING btree (vn_list_id, sort_order);


--
-- Name: vn_lists_public_created_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vn_lists_public_created_index ON public.vn_lists USING btree (is_public, created_at);


--
-- Name: vn_lists_type_public_created_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vn_lists_type_public_created_index ON public.vn_lists USING btree (type, is_public, created_at);


--
-- Name: vn_lists_user_created_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vn_lists_user_created_index ON public.vn_lists USING btree (user_id, created_at);


--
-- Name: vn_lists_user_public_created_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vn_lists_user_public_created_index ON public.vn_lists USING btree (user_id, is_public, created_at);


--
-- Name: vn_lists_user_type_created_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vn_lists_user_type_created_index ON public.vn_lists USING btree (user_id, type, created_at);


--
-- Name: vrc_version_label_context_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vrc_version_label_context_index ON public.version_route_variable_changes USING btree (game_version_id, label, context);


--
-- Name: vrl_version_is_ending_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vrl_version_is_ending_index ON public.version_route_labels USING btree (game_version_id, is_ending);


--
-- Name: vrl_version_is_scaffolding_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vrl_version_is_scaffolding_index ON public.version_route_labels USING btree (game_version_id, is_scaffolding);


--
-- Name: vrmc_version_label_menu_branch_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vrmc_version_label_menu_branch_index ON public.version_route_menu_choices USING btree (game_version_id, from_label, menu_branch);


--
-- Name: change_logs_y2025m01_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m01_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m01_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m01_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m01_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m01_expr_idx;


--
-- Name: change_logs_y2025m01_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m01_expr_idx1;


--
-- Name: change_logs_y2025m01_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m01_expr_idx2;


--
-- Name: change_logs_y2025m01_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m01_expr_timestamp_idx;


--
-- Name: change_logs_y2025m01_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m01_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m01_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m01_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m01_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m01_pkey;


--
-- Name: change_logs_y2025m01_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m01_timestamp_idx;


--
-- Name: change_logs_y2025m01_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m01_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m02_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m02_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m02_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m02_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m02_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m02_expr_idx;


--
-- Name: change_logs_y2025m02_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m02_expr_idx1;


--
-- Name: change_logs_y2025m02_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m02_expr_idx2;


--
-- Name: change_logs_y2025m02_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m02_expr_timestamp_idx;


--
-- Name: change_logs_y2025m02_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m02_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m02_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m02_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m02_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m02_pkey;


--
-- Name: change_logs_y2025m02_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m02_timestamp_idx;


--
-- Name: change_logs_y2025m02_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m02_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m03_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m03_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m03_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m03_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m03_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m03_expr_idx;


--
-- Name: change_logs_y2025m03_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m03_expr_idx1;


--
-- Name: change_logs_y2025m03_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m03_expr_idx2;


--
-- Name: change_logs_y2025m03_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m03_expr_timestamp_idx;


--
-- Name: change_logs_y2025m03_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m03_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m03_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m03_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m03_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m03_pkey;


--
-- Name: change_logs_y2025m03_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m03_timestamp_idx;


--
-- Name: change_logs_y2025m03_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m03_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m04_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m04_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m04_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m04_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m04_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m04_expr_idx;


--
-- Name: change_logs_y2025m04_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m04_expr_idx1;


--
-- Name: change_logs_y2025m04_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m04_expr_idx2;


--
-- Name: change_logs_y2025m04_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m04_expr_timestamp_idx;


--
-- Name: change_logs_y2025m04_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m04_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m04_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m04_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m04_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m04_pkey;


--
-- Name: change_logs_y2025m04_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m04_timestamp_idx;


--
-- Name: change_logs_y2025m04_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m04_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m05_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m05_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m05_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m05_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m05_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m05_expr_idx;


--
-- Name: change_logs_y2025m05_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m05_expr_idx1;


--
-- Name: change_logs_y2025m05_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m05_expr_idx2;


--
-- Name: change_logs_y2025m05_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m05_expr_timestamp_idx;


--
-- Name: change_logs_y2025m05_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m05_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m05_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m05_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m05_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m05_pkey;


--
-- Name: change_logs_y2025m05_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m05_timestamp_idx;


--
-- Name: change_logs_y2025m05_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m05_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m06_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m06_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m06_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m06_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m06_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m06_expr_idx;


--
-- Name: change_logs_y2025m06_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m06_expr_idx1;


--
-- Name: change_logs_y2025m06_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m06_expr_idx2;


--
-- Name: change_logs_y2025m06_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m06_expr_timestamp_idx;


--
-- Name: change_logs_y2025m06_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m06_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m06_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m06_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m06_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m06_pkey;


--
-- Name: change_logs_y2025m06_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m06_timestamp_idx;


--
-- Name: change_logs_y2025m06_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m06_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m07_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m07_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m07_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m07_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m07_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m07_expr_idx;


--
-- Name: change_logs_y2025m07_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m07_expr_idx1;


--
-- Name: change_logs_y2025m07_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m07_expr_idx2;


--
-- Name: change_logs_y2025m07_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m07_expr_timestamp_idx;


--
-- Name: change_logs_y2025m07_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m07_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m07_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m07_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m07_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m07_pkey;


--
-- Name: change_logs_y2025m07_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m07_timestamp_idx;


--
-- Name: change_logs_y2025m07_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m07_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m08_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m08_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m08_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m08_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m08_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m08_expr_idx;


--
-- Name: change_logs_y2025m08_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m08_expr_idx1;


--
-- Name: change_logs_y2025m08_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m08_expr_idx2;


--
-- Name: change_logs_y2025m08_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m08_expr_timestamp_idx;


--
-- Name: change_logs_y2025m08_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m08_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m08_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m08_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m08_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m08_pkey;


--
-- Name: change_logs_y2025m08_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m08_timestamp_idx;


--
-- Name: change_logs_y2025m08_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m08_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m09_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m09_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m09_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m09_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m09_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m09_expr_idx;


--
-- Name: change_logs_y2025m09_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m09_expr_idx1;


--
-- Name: change_logs_y2025m09_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m09_expr_idx2;


--
-- Name: change_logs_y2025m09_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m09_expr_timestamp_idx;


--
-- Name: change_logs_y2025m09_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m09_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m09_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m09_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m09_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m09_pkey;


--
-- Name: change_logs_y2025m09_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m09_timestamp_idx;


--
-- Name: change_logs_y2025m09_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m09_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m10_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m10_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m10_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m10_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m10_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m10_expr_idx;


--
-- Name: change_logs_y2025m10_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m10_expr_idx1;


--
-- Name: change_logs_y2025m10_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m10_expr_idx2;


--
-- Name: change_logs_y2025m10_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m10_expr_timestamp_idx;


--
-- Name: change_logs_y2025m10_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m10_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m10_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m10_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m10_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m10_pkey;


--
-- Name: change_logs_y2025m10_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m10_timestamp_idx;


--
-- Name: change_logs_y2025m10_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m10_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m11_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m11_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m11_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m11_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m11_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m11_expr_idx;


--
-- Name: change_logs_y2025m11_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m11_expr_idx1;


--
-- Name: change_logs_y2025m11_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m11_expr_idx2;


--
-- Name: change_logs_y2025m11_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m11_expr_timestamp_idx;


--
-- Name: change_logs_y2025m11_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m11_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m11_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m11_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m11_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m11_pkey;


--
-- Name: change_logs_y2025m11_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m11_timestamp_idx;


--
-- Name: change_logs_y2025m11_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m11_user_id_timestamp_idx;


--
-- Name: change_logs_y2025m12_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2025m12_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2025m12_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2025m12_event_type_timestamp_idx;


--
-- Name: change_logs_y2025m12_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2025m12_expr_idx;


--
-- Name: change_logs_y2025m12_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2025m12_expr_idx1;


--
-- Name: change_logs_y2025m12_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2025m12_expr_idx2;


--
-- Name: change_logs_y2025m12_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2025m12_expr_timestamp_idx;


--
-- Name: change_logs_y2025m12_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2025m12_expr_timestamp_idx1;


--
-- Name: change_logs_y2025m12_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2025m12_expr_timestamp_idx2;


--
-- Name: change_logs_y2025m12_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2025m12_pkey;


--
-- Name: change_logs_y2025m12_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2025m12_timestamp_idx;


--
-- Name: change_logs_y2025m12_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2025m12_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m01_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m01_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m01_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m01_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m01_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m01_expr_idx;


--
-- Name: change_logs_y2026m01_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m01_expr_idx1;


--
-- Name: change_logs_y2026m01_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m01_expr_idx2;


--
-- Name: change_logs_y2026m01_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m01_expr_timestamp_idx;


--
-- Name: change_logs_y2026m01_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m01_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m01_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m01_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m01_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m01_pkey;


--
-- Name: change_logs_y2026m01_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m01_timestamp_idx;


--
-- Name: change_logs_y2026m01_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m01_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m02_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m02_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m02_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m02_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m02_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m02_expr_idx;


--
-- Name: change_logs_y2026m02_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m02_expr_idx1;


--
-- Name: change_logs_y2026m02_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m02_expr_idx2;


--
-- Name: change_logs_y2026m02_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m02_expr_timestamp_idx;


--
-- Name: change_logs_y2026m02_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m02_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m02_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m02_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m02_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m02_pkey;


--
-- Name: change_logs_y2026m02_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m02_timestamp_idx;


--
-- Name: change_logs_y2026m02_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m02_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m03_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m03_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m03_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m03_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m03_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m03_expr_idx;


--
-- Name: change_logs_y2026m03_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m03_expr_idx1;


--
-- Name: change_logs_y2026m03_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m03_expr_idx2;


--
-- Name: change_logs_y2026m03_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m03_expr_timestamp_idx;


--
-- Name: change_logs_y2026m03_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m03_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m03_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m03_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m03_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m03_pkey;


--
-- Name: change_logs_y2026m03_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m03_timestamp_idx;


--
-- Name: change_logs_y2026m03_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m03_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m04_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m04_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m04_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m04_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m04_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m04_expr_idx;


--
-- Name: change_logs_y2026m04_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m04_expr_idx1;


--
-- Name: change_logs_y2026m04_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m04_expr_idx2;


--
-- Name: change_logs_y2026m04_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m04_expr_timestamp_idx;


--
-- Name: change_logs_y2026m04_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m04_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m04_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m04_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m04_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m04_pkey;


--
-- Name: change_logs_y2026m04_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m04_timestamp_idx;


--
-- Name: change_logs_y2026m04_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m04_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m05_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m05_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m05_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m05_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m05_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m05_expr_idx;


--
-- Name: change_logs_y2026m05_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m05_expr_idx1;


--
-- Name: change_logs_y2026m05_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m05_expr_idx2;


--
-- Name: change_logs_y2026m05_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m05_expr_timestamp_idx;


--
-- Name: change_logs_y2026m05_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m05_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m05_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m05_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m05_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m05_pkey;


--
-- Name: change_logs_y2026m05_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m05_timestamp_idx;


--
-- Name: change_logs_y2026m05_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m05_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m06_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m06_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m06_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m06_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m06_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m06_expr_idx;


--
-- Name: change_logs_y2026m06_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m06_expr_idx1;


--
-- Name: change_logs_y2026m06_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m06_expr_idx2;


--
-- Name: change_logs_y2026m06_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m06_expr_timestamp_idx;


--
-- Name: change_logs_y2026m06_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m06_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m06_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m06_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m06_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m06_pkey;


--
-- Name: change_logs_y2026m06_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m06_timestamp_idx;


--
-- Name: change_logs_y2026m06_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m06_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m07_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m07_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m07_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m07_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m07_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m07_expr_idx;


--
-- Name: change_logs_y2026m07_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m07_expr_idx1;


--
-- Name: change_logs_y2026m07_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m07_expr_idx2;


--
-- Name: change_logs_y2026m07_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m07_expr_timestamp_idx;


--
-- Name: change_logs_y2026m07_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m07_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m07_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m07_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m07_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m07_pkey;


--
-- Name: change_logs_y2026m07_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m07_timestamp_idx;


--
-- Name: change_logs_y2026m07_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m07_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m08_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m08_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m08_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m08_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m08_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m08_expr_idx;


--
-- Name: change_logs_y2026m08_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m08_expr_idx1;


--
-- Name: change_logs_y2026m08_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m08_expr_idx2;


--
-- Name: change_logs_y2026m08_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m08_expr_timestamp_idx;


--
-- Name: change_logs_y2026m08_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m08_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m08_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m08_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m08_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m08_pkey;


--
-- Name: change_logs_y2026m08_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m08_timestamp_idx;


--
-- Name: change_logs_y2026m08_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m08_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m09_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m09_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m09_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m09_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m09_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m09_expr_idx;


--
-- Name: change_logs_y2026m09_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m09_expr_idx1;


--
-- Name: change_logs_y2026m09_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m09_expr_idx2;


--
-- Name: change_logs_y2026m09_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m09_expr_timestamp_idx;


--
-- Name: change_logs_y2026m09_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m09_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m09_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m09_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m09_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m09_pkey;


--
-- Name: change_logs_y2026m09_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m09_timestamp_idx;


--
-- Name: change_logs_y2026m09_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m09_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m10_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m10_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m10_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m10_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m10_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m10_expr_idx;


--
-- Name: change_logs_y2026m10_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m10_expr_idx1;


--
-- Name: change_logs_y2026m10_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m10_expr_idx2;


--
-- Name: change_logs_y2026m10_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m10_expr_timestamp_idx;


--
-- Name: change_logs_y2026m10_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m10_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m10_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m10_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m10_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m10_pkey;


--
-- Name: change_logs_y2026m10_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m10_timestamp_idx;


--
-- Name: change_logs_y2026m10_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m10_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m11_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m11_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m11_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m11_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m11_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m11_expr_idx;


--
-- Name: change_logs_y2026m11_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m11_expr_idx1;


--
-- Name: change_logs_y2026m11_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m11_expr_idx2;


--
-- Name: change_logs_y2026m11_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m11_expr_timestamp_idx;


--
-- Name: change_logs_y2026m11_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m11_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m11_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m11_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m11_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m11_pkey;


--
-- Name: change_logs_y2026m11_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m11_timestamp_idx;


--
-- Name: change_logs_y2026m11_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m11_user_id_timestamp_idx;


--
-- Name: change_logs_y2026m12_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2026m12_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2026m12_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2026m12_event_type_timestamp_idx;


--
-- Name: change_logs_y2026m12_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2026m12_expr_idx;


--
-- Name: change_logs_y2026m12_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2026m12_expr_idx1;


--
-- Name: change_logs_y2026m12_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2026m12_expr_idx2;


--
-- Name: change_logs_y2026m12_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2026m12_expr_timestamp_idx;


--
-- Name: change_logs_y2026m12_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2026m12_expr_timestamp_idx1;


--
-- Name: change_logs_y2026m12_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2026m12_expr_timestamp_idx2;


--
-- Name: change_logs_y2026m12_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2026m12_pkey;


--
-- Name: change_logs_y2026m12_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2026m12_timestamp_idx;


--
-- Name: change_logs_y2026m12_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2026m12_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m01_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m01_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m01_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m01_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m01_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m01_expr_idx;


--
-- Name: change_logs_y2027m01_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m01_expr_idx1;


--
-- Name: change_logs_y2027m01_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m01_expr_idx2;


--
-- Name: change_logs_y2027m01_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m01_expr_timestamp_idx;


--
-- Name: change_logs_y2027m01_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m01_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m01_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m01_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m01_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m01_pkey;


--
-- Name: change_logs_y2027m01_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m01_timestamp_idx;


--
-- Name: change_logs_y2027m01_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m01_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m02_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m02_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m02_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m02_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m02_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m02_expr_idx;


--
-- Name: change_logs_y2027m02_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m02_expr_idx1;


--
-- Name: change_logs_y2027m02_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m02_expr_idx2;


--
-- Name: change_logs_y2027m02_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m02_expr_timestamp_idx;


--
-- Name: change_logs_y2027m02_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m02_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m02_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m02_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m02_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m02_pkey;


--
-- Name: change_logs_y2027m02_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m02_timestamp_idx;


--
-- Name: change_logs_y2027m02_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m02_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m03_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m03_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m03_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m03_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m03_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m03_expr_idx;


--
-- Name: change_logs_y2027m03_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m03_expr_idx1;


--
-- Name: change_logs_y2027m03_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m03_expr_idx2;


--
-- Name: change_logs_y2027m03_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m03_expr_timestamp_idx;


--
-- Name: change_logs_y2027m03_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m03_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m03_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m03_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m03_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m03_pkey;


--
-- Name: change_logs_y2027m03_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m03_timestamp_idx;


--
-- Name: change_logs_y2027m03_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m03_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m04_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m04_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m04_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m04_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m04_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m04_expr_idx;


--
-- Name: change_logs_y2027m04_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m04_expr_idx1;


--
-- Name: change_logs_y2027m04_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m04_expr_idx2;


--
-- Name: change_logs_y2027m04_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m04_expr_timestamp_idx;


--
-- Name: change_logs_y2027m04_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m04_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m04_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m04_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m04_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m04_pkey;


--
-- Name: change_logs_y2027m04_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m04_timestamp_idx;


--
-- Name: change_logs_y2027m04_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m04_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m05_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m05_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m05_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m05_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m05_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m05_expr_idx;


--
-- Name: change_logs_y2027m05_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m05_expr_idx1;


--
-- Name: change_logs_y2027m05_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m05_expr_idx2;


--
-- Name: change_logs_y2027m05_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m05_expr_timestamp_idx;


--
-- Name: change_logs_y2027m05_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m05_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m05_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m05_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m05_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m05_pkey;


--
-- Name: change_logs_y2027m05_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m05_timestamp_idx;


--
-- Name: change_logs_y2027m05_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m05_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m06_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m06_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m06_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m06_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m06_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m06_expr_idx;


--
-- Name: change_logs_y2027m06_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m06_expr_idx1;


--
-- Name: change_logs_y2027m06_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m06_expr_idx2;


--
-- Name: change_logs_y2027m06_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m06_expr_timestamp_idx;


--
-- Name: change_logs_y2027m06_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m06_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m06_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m06_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m06_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m06_pkey;


--
-- Name: change_logs_y2027m06_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m06_timestamp_idx;


--
-- Name: change_logs_y2027m06_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m06_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m07_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m07_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m07_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m07_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m07_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m07_expr_idx;


--
-- Name: change_logs_y2027m07_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m07_expr_idx1;


--
-- Name: change_logs_y2027m07_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m07_expr_idx2;


--
-- Name: change_logs_y2027m07_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m07_expr_timestamp_idx;


--
-- Name: change_logs_y2027m07_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m07_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m07_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m07_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m07_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m07_pkey;


--
-- Name: change_logs_y2027m07_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m07_timestamp_idx;


--
-- Name: change_logs_y2027m07_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m07_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m08_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m08_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m08_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m08_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m08_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m08_expr_idx;


--
-- Name: change_logs_y2027m08_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m08_expr_idx1;


--
-- Name: change_logs_y2027m08_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m08_expr_idx2;


--
-- Name: change_logs_y2027m08_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m08_expr_timestamp_idx;


--
-- Name: change_logs_y2027m08_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m08_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m08_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m08_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m08_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m08_pkey;


--
-- Name: change_logs_y2027m08_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m08_timestamp_idx;


--
-- Name: change_logs_y2027m08_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m08_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m09_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m09_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m09_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m09_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m09_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m09_expr_idx;


--
-- Name: change_logs_y2027m09_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m09_expr_idx1;


--
-- Name: change_logs_y2027m09_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m09_expr_idx2;


--
-- Name: change_logs_y2027m09_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m09_expr_timestamp_idx;


--
-- Name: change_logs_y2027m09_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m09_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m09_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m09_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m09_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m09_pkey;


--
-- Name: change_logs_y2027m09_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m09_timestamp_idx;


--
-- Name: change_logs_y2027m09_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m09_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m10_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m10_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m10_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m10_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m10_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m10_expr_idx;


--
-- Name: change_logs_y2027m10_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m10_expr_idx1;


--
-- Name: change_logs_y2027m10_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m10_expr_idx2;


--
-- Name: change_logs_y2027m10_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m10_expr_timestamp_idx;


--
-- Name: change_logs_y2027m10_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m10_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m10_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m10_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m10_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m10_pkey;


--
-- Name: change_logs_y2027m10_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m10_timestamp_idx;


--
-- Name: change_logs_y2027m10_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m10_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m11_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m11_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m11_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m11_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m11_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m11_expr_idx;


--
-- Name: change_logs_y2027m11_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m11_expr_idx1;


--
-- Name: change_logs_y2027m11_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m11_expr_idx2;


--
-- Name: change_logs_y2027m11_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m11_expr_timestamp_idx;


--
-- Name: change_logs_y2027m11_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m11_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m11_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m11_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m11_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m11_pkey;


--
-- Name: change_logs_y2027m11_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m11_timestamp_idx;


--
-- Name: change_logs_y2027m11_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m11_user_id_timestamp_idx;


--
-- Name: change_logs_y2027m12_entity_type_entity_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_entity ATTACH PARTITION public.change_logs_y2027m12_entity_type_entity_id_timestamp_idx;


--
-- Name: change_logs_y2027m12_event_type_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_event ATTACH PARTITION public.change_logs_y2027m12_event_type_timestamp_idx;


--
-- Name: change_logs_y2027m12_expr_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_id ATTACH PARTITION public.change_logs_y2027m12_expr_idx;


--
-- Name: change_logs_y2027m12_expr_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_id ATTACH PARTITION public.change_logs_y2027m12_expr_idx1;


--
-- Name: change_logs_y2027m12_expr_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_name ATTACH PARTITION public.change_logs_y2027m12_expr_idx2;


--
-- Name: change_logs_y2027m12_expr_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_request_time ATTACH PARTITION public.change_logs_y2027m12_expr_timestamp_idx;


--
-- Name: change_logs_y2027m12_expr_timestamp_idx1; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_session_time ATTACH PARTITION public.change_logs_y2027m12_expr_timestamp_idx1;


--
-- Name: change_logs_y2027m12_expr_timestamp_idx2; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_command_time ATTACH PARTITION public.change_logs_y2027m12_expr_timestamp_idx2;


--
-- Name: change_logs_y2027m12_pkey; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.change_logs_pkey ATTACH PARTITION public.change_logs_y2027m12_pkey;


--
-- Name: change_logs_y2027m12_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_timestamp ATTACH PARTITION public.change_logs_y2027m12_timestamp_idx;


--
-- Name: change_logs_y2027m12_user_id_timestamp_idx; Type: INDEX ATTACH; Schema: public; Owner: -
--

ALTER INDEX public.idx_change_logs_user ATTACH PARTITION public.change_logs_y2027m12_user_id_timestamp_idx;


--
-- Name: games update_game_slug_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_game_slug_trigger BEFORE INSERT OR UPDATE ON public.games FOR EACH ROW EXECUTE FUNCTION public.update_game_slug();


--
-- Name: game_versions update_game_version_latest_flag_trigger; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER update_game_version_latest_flag_trigger AFTER INSERT OR DELETE OR UPDATE OF published_at ON public.game_versions FOR EACH ROW EXECUTE FUNCTION public.update_game_version_latest_flag();


--
-- Name: addition_request_users addition_request_users_addition_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_request_users
    ADD CONSTRAINT addition_request_users_addition_request_id_foreign FOREIGN KEY (addition_request_id) REFERENCES public.addition_requests(id) ON DELETE CASCADE;


--
-- Name: addition_request_users addition_request_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_request_users
    ADD CONSTRAINT addition_request_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: addition_requests addition_requests_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_requests
    ADD CONSTRAINT addition_requests_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE SET NULL;


--
-- Name: addition_requests addition_requests_reviewed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addition_requests
    ADD CONSTRAINT addition_requests_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: android_builds android_builds_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.android_builds
    ADD CONSTRAINT android_builds_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: android_builds android_builds_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.android_builds
    ADD CONSTRAINT android_builds_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: android_builds android_builds_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.android_builds
    ADD CONSTRAINT android_builds_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: bug_report_comments bug_report_comments_bug_report_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bug_report_comments
    ADD CONSTRAINT bug_report_comments_bug_report_id_foreign FOREIGN KEY (bug_report_id) REFERENCES public.bug_reports(id) ON DELETE CASCADE;


--
-- Name: bug_report_comments bug_report_comments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bug_report_comments
    ADD CONSTRAINT bug_report_comments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: bug_reports bug_reports_resolved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bug_reports
    ADD CONSTRAINT bug_reports_resolved_by_foreign FOREIGN KEY (resolved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: bug_reports bug_reports_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bug_reports
    ADD CONSTRAINT bug_reports_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: change_logs change_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE public.change_logs
    ADD CONSTRAINT change_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: version_character_stats character_version_stats_character_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT character_version_stats_character_id_foreign FOREIGN KEY (character_id) REFERENCES public.characters(id) ON DELETE CASCADE;


--
-- Name: version_character_stats character_version_stats_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_character_stats
    ADD CONSTRAINT character_version_stats_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: characters characters_first_seen_in_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_first_seen_in_version_id_foreign FOREIGN KEY (first_seen_in_version_id) REFERENCES public.game_versions(id) ON DELETE SET NULL;


--
-- Name: characters characters_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: characters characters_last_seen_in_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.characters
    ADD CONSTRAINT characters_last_seen_in_version_id_foreign FOREIGN KEY (last_seen_in_version_id) REFERENCES public.game_versions(id) ON DELETE SET NULL;


--
-- Name: click_stats click_stats_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.click_stats
    ADD CONSTRAINT click_stats_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: click_stats click_stats_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.click_stats
    ADD CONSTRAINT click_stats_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: discord_channel_announcements discord_channel_announcements_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_channel_announcements
    ADD CONSTRAINT discord_channel_announcements_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: discord_channel_announcements discord_channel_announcements_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_channel_announcements
    ADD CONSTRAINT discord_channel_announcements_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: discord_notification_history discord_notification_history_discord_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_notification_history
    ADD CONSTRAINT discord_notification_history_discord_server_id_foreign FOREIGN KEY (discord_server_id) REFERENCES public.discord_servers(id) ON DELETE CASCADE;


--
-- Name: discord_notification_history discord_notification_history_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_notification_history
    ADD CONSTRAINT discord_notification_history_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: discord_server_configs discord_server_configs_discord_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_configs
    ADD CONSTRAINT discord_server_configs_discord_server_id_foreign FOREIGN KEY (discord_server_id) REFERENCES public.discord_servers(id) ON DELETE CASCADE;


--
-- Name: discord_server_game_overrides discord_server_game_overrides_discord_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_game_overrides
    ADD CONSTRAINT discord_server_game_overrides_discord_server_id_foreign FOREIGN KEY (discord_server_id) REFERENCES public.discord_servers(id) ON DELETE CASCADE;


--
-- Name: discord_server_game_overrides discord_server_game_overrides_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_game_overrides
    ADD CONSTRAINT discord_server_game_overrides_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: discord_server_games discord_server_games_discord_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_games
    ADD CONSTRAINT discord_server_games_discord_server_id_foreign FOREIGN KEY (discord_server_id) REFERENCES public.discord_servers(id) ON DELETE CASCADE;


--
-- Name: discord_server_games discord_server_games_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_games
    ADD CONSTRAINT discord_server_games_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: discord_server_members discord_server_members_discord_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_members
    ADD CONSTRAINT discord_server_members_discord_server_id_foreign FOREIGN KEY (discord_server_id) REFERENCES public.discord_servers(id) ON DELETE CASCADE;


--
-- Name: discord_server_members discord_server_members_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_members
    ADD CONSTRAINT discord_server_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: discord_server_tags discord_server_tags_discord_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_server_tags
    ADD CONSTRAINT discord_server_tags_discord_server_id_foreign FOREIGN KEY (discord_server_id) REFERENCES public.discord_servers(id) ON DELETE CASCADE;


--
-- Name: discord_servers discord_servers_owner_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.discord_servers
    ADD CONSTRAINT discord_servers_owner_user_id_foreign FOREIGN KEY (owner_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: monitored_scheduled_task_log_items fk_scheduled_task_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.monitored_scheduled_task_log_items
    ADD CONSTRAINT fk_scheduled_task_id FOREIGN KEY (monitored_scheduled_task_id) REFERENCES public.monitored_scheduled_tasks(id) ON DELETE CASCADE;


--
-- Name: game_discord_subscriptions game_discord_subscriptions_discord_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_discord_subscriptions
    ADD CONSTRAINT game_discord_subscriptions_discord_server_id_foreign FOREIGN KEY (discord_server_id) REFERENCES public.discord_servers(id) ON DELETE CASCADE;


--
-- Name: game_discord_subscriptions game_discord_subscriptions_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_discord_subscriptions
    ADD CONSTRAINT game_discord_subscriptions_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: game_game_jam game_game_jam_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam
    ADD CONSTRAINT game_game_jam_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: game_game_jam game_game_jam_game_jam_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_game_jam
    ADD CONSTRAINT game_game_jam_game_jam_id_foreign FOREIGN KEY (game_jam_id) REFERENCES public.game_jams(id) ON DELETE CASCADE;


--
-- Name: game_tag game_tag_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_tag
    ADD CONSTRAINT game_tag_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: game_tag game_tag_tag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_tag
    ADD CONSTRAINT game_tag_tag_id_foreign FOREIGN KEY (tag_id) REFERENCES public.tags(id) ON DELETE CASCADE;


--
-- Name: game_versions game_versions_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.game_versions
    ADD CONSTRAINT game_versions_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id);


--
-- Name: games games_custom_page_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_custom_page_updated_by_foreign FOREIGN KEY (custom_page_updated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: games games_source_language_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.games
    ADD CONSTRAINT games_source_language_id_foreign FOREIGN KEY (source_language_id) REFERENCES public.iso_639_3_languages(id) ON DELETE SET NULL;


--
-- Name: language_mappings language_mappings_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.language_mappings
    ADD CONSTRAINT language_mappings_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: notification_history notification_history_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: notification_history notification_history_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: notification_history notification_history_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_history
    ADD CONSTRAINT notification_history_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: notification_queue notification_queue_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: notification_queue notification_queue_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: notification_queue notification_queue_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notification_queue
    ADD CONSTRAINT notification_queue_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: push_subscriptions push_subscriptions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.push_subscriptions
    ADD CONSTRAINT push_subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


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
-- Name: ratings ratings_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ratings
    ADD CONSTRAINT ratings_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: review_reports review_reports_rating_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.review_reports
    ADD CONSTRAINT review_reports_rating_id_foreign FOREIGN KEY (rating_id) REFERENCES public.ratings(id) ON DELETE CASCADE;


--
-- Name: review_reports review_reports_reporter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.review_reports
    ADD CONSTRAINT review_reports_reporter_id_foreign FOREIGN KEY (reporter_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: review_reports review_reports_reviewed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.review_reports
    ADD CONSTRAINT review_reports_reviewed_by_foreign FOREIGN KEY (reviewed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: social_accounts social_accounts_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.social_accounts
    ADD CONSTRAINT social_accounts_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_game_progress user_game_progress_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: user_game_progress user_game_progress_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE SET NULL;


--
-- Name: user_game_progress user_game_progress_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_game_progress
    ADD CONSTRAINT user_game_progress_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_ignored_games user_ignored_games_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ignored_games
    ADD CONSTRAINT user_ignored_games_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: user_ignored_games user_ignored_games_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_ignored_games
    ADD CONSTRAINT user_ignored_games_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_notification_preferences user_notification_preferences_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_notification_preferences
    ADD CONSTRAINT user_notification_preferences_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_preferences user_preferences_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_preferences
    ADD CONSTRAINT user_preferences_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: version_dialogue_lines version_dialogue_lines_character_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_dialogue_lines
    ADD CONSTRAINT version_dialogue_lines_character_id_foreign FOREIGN KEY (character_id) REFERENCES public.characters(id) ON DELETE SET NULL;


--
-- Name: version_dialogue_lines version_dialogue_lines_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_dialogue_lines
    ADD CONSTRAINT version_dialogue_lines_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_file_categories version_file_categories_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_categories
    ADD CONSTRAINT version_file_categories_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_file_types version_file_types_version_file_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_file_types
    ADD CONSTRAINT version_file_types_version_file_category_id_foreign FOREIGN KEY (version_file_category_id) REFERENCES public.version_file_categories(id) ON DELETE CASCADE;


--
-- Name: version_language_stats version_language_stats_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_language_stats
    ADD CONSTRAINT version_language_stats_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_route_edges version_route_edges_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_edges
    ADD CONSTRAINT version_route_edges_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_route_labels version_route_labels_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_labels
    ADD CONSTRAINT version_route_labels_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_route_menu_choices version_route_menu_choices_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_menu_choices
    ADD CONSTRAINT version_route_menu_choices_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_route_paths version_route_paths_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_paths
    ADD CONSTRAINT version_route_paths_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_route_variable_changes version_route_variable_changes_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_variable_changes
    ADD CONSTRAINT version_route_variable_changes_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_route_variables version_route_variables_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_route_variables
    ADD CONSTRAINT version_route_variables_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_supported_languages version_supported_languages_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages
    ADD CONSTRAINT version_supported_languages_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_supported_languages version_supported_languages_iso_code_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_supported_languages
    ADD CONSTRAINT version_supported_languages_iso_code_foreign FOREIGN KEY (iso_code) REFERENCES public.iso_639_3_languages(id) ON DELETE RESTRICT;


--
-- Name: version_word_frequencies version_word_frequencies_game_version_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_word_frequencies
    ADD CONSTRAINT version_word_frequencies_game_version_id_foreign FOREIGN KEY (game_version_id) REFERENCES public.game_versions(id) ON DELETE CASCADE;


--
-- Name: version_word_frequencies version_word_frequencies_iso_code_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.version_word_frequencies
    ADD CONSTRAINT version_word_frequencies_iso_code_foreign FOREIGN KEY (iso_code) REFERENCES public.iso_639_3_languages(id) ON DELETE CASCADE;


--
-- Name: vn_list_entries vn_list_entries_game_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries
    ADD CONSTRAINT vn_list_entries_game_id_foreign FOREIGN KEY (game_id) REFERENCES public.games(id) ON DELETE CASCADE;


--
-- Name: vn_list_entries vn_list_entries_vn_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_list_entries
    ADD CONSTRAINT vn_list_entries_vn_list_id_foreign FOREIGN KEY (vn_list_id) REFERENCES public.vn_lists(id) ON DELETE CASCADE;


--
-- Name: vn_lists vn_lists_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vn_lists
    ADD CONSTRAINT vn_lists_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict hfHDefaRD2eMUMSUDLub5NUYmzUUykJLOttZykwY8JMhPHeZlwUTyseexhp14xI

--
-- PostgreSQL database dump
--

\restrict 2272nJyogkEXwy0yfCwGKmDExCUkfiMJ4PCfmlXrAfbOvE46pBbmz8aR0dEl0lC

-- Dumped from database version 18.4 (Debian 18.4-1.pgdg13+1)
-- Dumped by pg_dump version 18.4 (Debian 18.4-1.pgdg13+1)

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
9	2024_05_12_211909_modify_raters_table	2
10	2024_05_12_211954_create_game_rater_table	2
11	2024_05_13_200336_drop_game_rater_table	3
12	2024_05_13_200810_modify_games_table	3
13	2024_05_18_233635_modify_games_table	4
14	2024_07_25_193937_add_uploads_column_to_games_table	5
15	2024_12_28_001000_create_iso_639_3_languages_table	6
16	2024_12_28_001100_create_language_mappings_table	6
17	2024_12_28_001200_create_version_language_stats_table	6
18	2024_12_28_001300_create_version_character_stats_table	6
19	2024_12_28_114217_create_game_supported_languages_table	6
20	2024_12_28_114259_migrate_version_stats	6
21	2024_12_28_114327_modify_games_table	6
22	2024_12_28_150226_rename_boolean_columns	6
23	2024_12_28_233201_add_is_latest_to_game_versions_table	6
24	2024_12_28_234040_add_indices_to_version_tables	6
25	2025_01_05_174709_add_slug_to_games_table	6
26	2025_01_05_230049_drop_stats_columns_from_game_versions_table	6
27	2025_01_06_105417_create_processed_events_table	7
28	2025_01_06_130710_add_is_feedless_to_games_table	8
29	2025_01_06_134332_drop_duplicate_columns_from_games_table	8
30	2025_01_10_172021_add_slug_trigger_for_games_table	9
31	2025_01_10_220426_add_source_language_to_games_table	10
32	2025_01_10_221901_drop_game_supported_languages_table	10
33	2025_01_17_111449_set_default_values_in_database	11
34	2025_01_17_182345_add_default_columns_to_processed_events_table	12
35	2025_01_17_190458_remove_processed_at_column_from_processed_events_table	12
36	2025_01_19_163611_add_default_columns_to_discord_users_table	13
37	2025_01_19_201905_migrate_version_character_stats	14
38	2025_01_21_185728_add_display_name_corrections_to_character_table	15
39	2025_01_28_220853_create_version_supported_languages_table	16
40	2025_01_29_222525_add_index_to_ratings_table	17
41	2025_01_24_135236_add_file_stats_tables	18
42	2025_02_02_000804_increase_extension_column_length_in_version_file_types_table	19
43	2025_02_02_180621_create_schedule_monitor_tables	20
44	2025_02_11_234533_add_optimized_thumbnails_to_games_table	21
45	2025_02_15_103117_add_alias_column_to_raters_table	22
46	2025_02_16_143350_add_weight_to_raters_table	23
47	2025_02_16_180625_add_scores_to_games_table	23
48	2025_02_16_191716_add_suspicious_columns_to_raters_table	23
49	2025_02_16_231617_create_cache_table	24
50	2025_02_16_234103_add_indices_to_ratings_and_raters_tables	25
51	2025_02_17_010819_add_raw_weighted_rating_to_games_table	26
52	2025_03_02_001118_create_version_dialogue_lines_table	27
53	2025_03_23_100103_add_game_id_column_to_language_mappings_table	28
54	2025_03_23_124716_add_is_available_to_version_supported_languages_table	29
55	2024_03_21_000001_create_social_accounts_table	30
56	2025_03_21_121537_nullable_email_column_in_users_table	30
57	2025_03_21_163104_add_avatar_column_to_users_table	30
58	2025_03_23_000645_drop_weighted_scores_columns	30
59	2024_03_22_000001_create_vn_lists_table	31
60	2024_03_22_000002_create_vn_list_entries_table	31
61	2024_03_25_000001_remove_rating_from_vn_list_entries	31
62	2024_03_25_000002_add_sort_order_to_vn_list_entries	31
63	2025_03_23_194354_create_user_game_progress_and_updates	31
64	2025_03_25_000001_create_update_notifications_table	32
65	2025_03_26_000001_create_user_notification_preferences_table	32
66	2025_03_26_221629_add_notification_digest_to_user_notification_preferences	32
67	2025_04_03_000001_create_push_subscriptions_table	33
68	2025_04_03_000002_create_notification_queue_table	33
69	2025_04_03_000003_add_receive_updates_to_user_game_progress	33
70	2025_04_04_172600_add_meta_data_to_notification_queue	34
71	2024_03_26_000001_create_import_states_table	35
72	2025_04_06_172154_add_is_admin_to_users_table	36
73	2025_04_06_181624_change_avatar_column_type_in_users_table	36
74	2025_05_01_000000_add_additional_game_details	36
75	2025_05_01_000001_create_game_jams_table	36
76	2025_05_01_000002_add_needs_details_fetch_to_game_jams	36
77	2025_04_09_231946_add_custom_css_to_games_table	37
78	2025_04_13_005722_remove_columns_from_game_jams_table	38
79	2025_04_13_163436_add_criteria_rankings_to_game_game_jam_table	39
80	2025_04_13_190609_add_private_notes_to_vn_list_entries	40
81	2025_05_15_000000_add_paid_and_demo_columns_to_games_table	41
82	2025_04_14_190104_remove_suggested_price_from_games_table	42
83	2025_04_21_183751_add_blur_screenshots_to_games_table	43
84	2025_04_27_170851_remove_alias_column_from_raters_table	44
85	2023_10_15_000000_create_android_builds_table	45
86	2025_04_27_225657_create_jobs_table	45
87	2025_05_17_200202_restructure_tags_system	46
88	2025_05_18_113100_remove_tags_from_games_table	46
89	2025_06_08_000000_add_notification_constraints_and_indexes	47
90	2025_07_12_140128_create_addition_requests_table	48
91	2025_07_25_005336_add_is_suspended_to_games_table	49
92	2025_07_25_164559_add_additional_links_to_games_table	50
93	2025_07_25_213744_add_sale_discount_percent_to_games_table	51
94	2025_07_26_121347_add_first_visible_at_to_games_table	52
95	2025_07_26_124156_create_change_logs_table	52
96	2025_07_26_143206_add_request_session_indexes_to_change_logs_table	52
97	2025_07_26_154835_add_command_index_to_change_logs_table	52
98	2025_07_26_160000_create_system_audit_user	52
99	2025_07_26_170000_add_timestamps_to_change_logs_table	52
100	2025_07_27_080014_create_click_stats_table	53
101	2025_07_27_164239_add_editable_project_page_fields_to_games_table	54
102	2025_07_27_200034_increase_referrer_field_size_in_click_stats_table	54
103	2025_07_29_220418_drop_unused_rating_columns_from_games_table	55
104	2025_07_29_235303_add_user_id_to_click_stats_table	56
105	2025_07_30_000000_add_rating_columns_to_games_table	57
106	2025_07_30_000001_remove_rating_columns_from_game_versions_table	57
107	2025_08_15_000000_add_vn_list_performance_indexes	58
108	2025_08_17_000001_add_indexes_for_ratings_queries	58
109	2025_08_18_204806_fix_unique_dialogue_texts_search_vector_index	58
110	2025_09_07_095049_create_teams_table	59
111	2025_09_07_095053_create_team_members_table	59
112	2025_09_07_095058_create_team_roles_table	59
113	2025_09_07_095101_create_team_permissions_table	59
114	2025_09_07_095105_create_team_games_table	59
115	2025_09_07_095109_create_team_invitations_table	59
116	2025_09_07_095113_create_team_activity_logs_table	59
117	2025_09_07_100607_add_foreign_keys_to_team_tables	59
118	2025_09_08_120000_update_team_invitations_add_invited_user_id	59
119	2025_09_08_121000_drop_email_from_team_invitations	59
120	2025_09_08_130000_create_notifications_table	59
121	2025_09_09_221807_add_character_demographics_to_characters_table	59
122	2025_10_11_003458_add_view_mode_to_games_table	59
123	2025_10_12_210251_add_itchio_game_ids_to_social_accounts_table	59
124	2025_10_14_000000_add_unique_constraint_to_game_versions	60
125	2025_10_15_144010_increase_social_accounts_token_column_size	61
126	2025_10_16_000000_create_news_table	62
127	2025_10_28_075501_add_custom_name_to_games_table	63
128	2025_10_19_000000_setup_games_table_schema	64
129	2025_10_19_000001_create_discord_servers_table	64
130	2025_10_19_000002_create_discord_server_configs_table	64
131	2025_10_19_000003_create_game_discord_subscriptions_table	64
132	2025_10_19_000004_create_discord_server_tags_table	64
133	2025_10_19_000005_create_discord_notification_history_table	64
134	2025_10_19_000006_create_discord_server_members_table	64
135	2025_10_19_000007_create_discord_server_games_table	64
136	2025_10_20_000000_make_addition_requests_platform_agnostic	64
137	2025_10_20_000001_backfill_addition_request_platforms	64
138	2025_10_20_100000_add_steam_metadata_fields_to_games	64
139	2025_10_20_200000_make_itch_id_nullable	64
140	2025_10_20_210000_add_external_review_tracking_fields	64
141	2025_10_20_220000_remove_user_id_unique_constraint_from_raters	64
142	2025_10_20_221140_rename_user_id_to_itch_id_in_raters_table	64
143	2025_10_20_230000_make_event_id_nullable_and_remove_unique_constraint	64
144	2025_10_23_000000_create_user_ignored_games_table	64
145	2025_10_23_003237_drop_blur_screenshots_from_games_table	64
146	2025_11_08_230916_split_screenshots_into_original_and_optimized	64
147	2025_11_17_195709_fix_null_game_slugs_and_add_constraint	65
148	2025_11_18_184701_add_discord_notified_at_to_addition_requests_table	65
149	2025_11_24_142724_add_currency_to_games_table	65
150	2025_11_29_011739_create_version_word_frequencies_table	65
151	2025_12_06_162427_create_bug_reports_table	65
152	2025_12_07_000000_drop_optimized_screenshots_column	65
153	2025_12_07_122628_replace_is_suspended_with_is_delisted_on_games	65
154	2026_01_06_122313_create_bug_report_comments_table	65
155	2026_01_06_131202_drop_news_table	65
156	2026_01_06_133103_add_is_closed_to_bug_reports_table	65
157	2026_03_08_000001_add_user_id_to_ratings_table	65
158	2026_03_08_000002_create_review_reports_table	65
159	2026_03_08_000004_create_user_preferences_table	65
160	2026_03_08_000005_add_is_review_banned_to_users_table	65
161	2026_03_08_000005_add_tag_id_index_to_game_tag_table	65
162	2026_03_08_000006_backfill_rater_external_platform	65
163	2026_03_08_000006_make_rater_id_nullable_on_ratings_table	65
164	2026_03_08_000007_add_fvn_li_to_ratings_source_platform_check	65
165	2026_03_08_000008_add_user_visible_published_at_index_to_ratings	65
166	2026_03_08_000009_add_is_review_banned_to_raters_table	65
167	2026_03_09_000001_add_excluded_tags_to_user_preferences_table	65
168	2026_03_28_000001_create_version_route_edges_table	65
169	2026_03_28_000002_add_route_variable_tracking	65
170	2026_03_29_185118_add_condition_to_version_route_edges_table	65
171	2026_03_30_000001_add_routing_and_embeds_to_discord_server_configs_table	65
172	2026_03_30_000002_create_discord_server_game_overrides_table	65
173	2026_03_30_000003_add_payload_to_discord_notification_history_table	65
174	2026_03_30_000004_add_available_channels_to_discord_servers_table	65
175	2026_03_30_000005_add_batch_key_to_discord_notification_history_table	65
176	2026_03_30_000006_add_processing_to_discord_notification_history_delivery_status	65
177	2026_03_30_000007_add_processing_to_discord_notification_history_delivery_status_check	65
178	2026_03_30_000008_add_indexes_to_discord_notification_history_table	65
179	2026_03_31_000002_create_version_route_paths_table	65
180	2026_04_05_104753_widen_context_column_on_version_route_variable_changes	65
181	2026_04_05_112019_add_prompt_to_version_route_menu_choices	65
182	2026_04_05_121005_add_translations_to_version_route_menu_choices	65
183	2026_04_05_175602_ensure_context_is_text_on_version_route_variable_changes	65
184	2026_04_05_210534_add_route_graph_data_to_game_versions	65
185	2026_04_21_000001_add_production_performance_indexes	65
186	2026_04_21_000002_add_route_variable_changes_context_index	65
187	2026_04_21_000003_create_2026_2027_change_log_partitions	65
188	2026_04_24_000001_add_menu_line_to_version_route_menu_choices	65
189	2026_04_24_000002_add_menu_scope_to_version_route_menu_choices	65
190	2026_04_24_000003_add_menu_condition_stack_to_version_route_menu_choices	65
191	2026_04_24_000004_add_condition_to_version_route_variable_changes	65
192	2026_04_27_000001_add_returns_to_caller_to_version_route_labels	65
193	2026_05_02_000001_create_sessions_table	65
194	2026_05_03_000001_ensure_discord_users_id_default	65
195	2026_05_03_000002_allow_manual_discord_notifications_without_game	65
196	2026_05_10_000001_ensure_system_audit_user	65
197	2026_05_23_000001_add_trending_score_to_games_table	65
198	2026_05_23_000002_add_click_stats_trending_score_index	65
199	2026_05_23_000003_add_is_stats_extraction_disabled_to_games_table	65
200	2026_05_23_000004_backfill_is_stats_extraction_disabled_for_demo_only_games	65
201	2026_07_12_000001_create_discord_channel_announcements_table	65
202	2026_07_12_000002_drop_discord_users_table	65
203	2026_07_12_000003_add_catalog_sync_fields_to_discord_tables	65
204	2026_07_15_000001_add_unreachable_route_graph_data_to_game_versions	65
205	2026_07_25_153828_add_externally_invoked_to_version_route_labels	65
206	2026_07_25_234745_add_is_scaffolding_to_version_route_labels	65
207	2026_07_27_000001_add_bot_reason_to_click_stats	65
208	2026_08_03_000001_drop_unused_team_tables	65
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 208, true);


--
-- PostgreSQL database dump complete
--

\unrestrict 2272nJyogkEXwy0yfCwGKmDExCUkfiMJ4PCfmlXrAfbOvE46pBbmz8aR0dEl0lC

