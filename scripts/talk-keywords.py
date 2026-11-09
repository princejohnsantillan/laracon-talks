#!/usr/bin/env python3
"""
Builds database/data/talk-keywords.json: for each talk, the 30 most distinctive words said in it,
so search can find a talk by what it covers and not only by its title or summary.

1. Fetch each video's English captions (manual where the video has them, otherwise YouTube's
   automatic ones) with yt-dlp, into a directory:

     yt-dlp --skip-download --write-subs --write-auto-subs --sub-langs en --sub-format json3 \
       -o "%(id)s.%(ext)s" -a urls.txt

2. Run: python3 scripts/talk-keywords.py database/data/talks.json <captions-dir> \
       database/data/talk-keywords.json database/data/talk-said.txt

The second file lists, for every word of three or more letters said in any talk, the talks that say
it and how often, so a search can check everything said in a talk (see TalkController::said()).
Only the words are kept, not the captions: neither file keeps their order.
"""
# How words are chosen. Distinctive terms each talk says, from its English captions (manual if the video has them,
# otherwise YouTube's automatic ones). Parameters were fixed before any benchmark run:
#   - terms are single words of 3+ letters that are not stopwords or numbers (search matches
#     each word separately, so phrases add nothing);
#   - a word must be said at least twice in the talk;
#   - score = times said x log(talks / talks that say it), across the whole archive;
#   - words already in the talk's title, summary, speaker or conference are skipped;
#   - the 30 highest-scoring words are kept.
# A larger index (up to 90 words for long talks plus 10 two-word phrases per talk) was tried in round 9:
# on a blind held-out set it found 28 of 45 talks against 27 with these 30 words, for 41 KB more
# gzipped, so it was not kept.
# Talks that are one segment of a livestream use the captions from their start to the next talk's start.
import collections
import json
import math
import os
import re
import sys

TOP = 30
MIN_COUNT = 2

STOP = set("""
a about above after again against all almost alone along already also although always am among an and another any anybody anyone
anything anyway anywhere are aren around as ask asked at away back be became because become becomes been before began behind being
believe below best better between big both but by call called came can cannot cant could couldn did didn different do does doesn
doing don done down during each easy else end enough even ever every everybody everyone everything example exactly far few find
first for found from full further gave get gets getting give given gives go goes going gone good got gotten great had hadn has hasn
have haven having he her here hers herself him himself his how however i if im in instead into is isn it its itself just keep kind
knew know known last least less let lets like likely little long look looking looks lot lots made make makes making many may maybe
me mean means might mine more most mostly much must my myself need needed needs never new next nice no nobody none nor not nothing
now of off often oh ok okay old on once one ones only onto or other others otherwise our ours ourselves out over own part people
perhaps place point pretty probably put quite rather re really right said same saw say saying says second see seem seems seen
several shall she should show showed shown shows side simply since so some somebody someone something sometimes somewhere soon
sort start started still stuff such sure take taken takes talk talked talking talks tell than thank thanks that thats the their
theirs them themselves then there therefore these they theyre thing things think thinking third this those though thought three
through thus time times to today together too took toward towards try trying turn two under until up upon us use used uses using
very via want wanted wants was wasn way ways we well went were weren what whatever when where whether which while who whole whom
whose why will with within without won wonder word words work works world would wouldn yeah yes yet you your youre yours yourself
yourselves ll ve gonna wanna gotta um uh uhm hmm mm ah applause laughter music cheering yep nope alright guys okay basically
actually literally totally kinda sorta definitely obviously cool awesome amazing super stuff bit bunch couple folks everybody
hey hello hi morning afternoon evening day days year years week weeks minute minutes hour hours today tonight tomorrow yesterday
able across ago ahead anymore apparently aside asking bad big came certain chance change changed clear close come comes coming
course deal different doing easier else entire especially fact feel feeling felt fine four five getting goes gone half happen
happened happens hard head hear heard help helps hope huge idea ideas important interesting job kinds later leave left level live
lives longer love mind moment move moved much name named number numbers open pick play pull quick quickly read ready real reason
remember run running saying set short slide slides small someone sorry stage stand step stop talk tell thank things together top
trying understand usually wait watch whatever wrong yourself zero six seven eight nine ten hundred thousand million first second
""".split())


def words(text):
    return re.findall(r"[a-z][a-z0-9]+", text.lower().replace("'", ""))


def caption_events(path):
    data = json.load(open(path))
    for event in data.get("events", []):
        text = "".join(seg.get("utf8", "") for seg in event.get("segs") or [])
        if text.strip():
            yield event.get("tStartMs", 0) / 1000, re.sub(r"\[[^\]]*\]", " ", text)


def video_and_start(url):
    vid = re.search(r"v=([\w-]{11})", url).group(1)
    t = re.search(r"[?&]t=(\d+)", url)
    return vid, int(t.group(1)) if t else 0


def caption_file(subs, vid):
    for name in (f"{vid}.en.json3", f"{vid}.en-orig.json3"):
        if os.path.exists(os.path.join(subs, name)):
            return os.path.join(subs, name)
    return None


def candidates(tokens):
    counts = collections.Counter()
    for w in tokens:
        if len(w) >= 3 and w not in STOP and not w.isdigit():
            counts[w] += 1
    return {term: n for term, n in counts.items() if n >= MIN_COUNT}


def write_said(talks, texts, path):
    """
    One line per word: the word, then each talk that says it as its position in the "@talks" line,
    with ":count" when it says it more than once. "@unindexed" lists the words left out because
    nearly every talk says them; the server treats those as said by every talk.
    """
    urls = [talk["youtube_url"] for talk in talks if talk["youtube_url"] in texts]
    position = {url: index for index, url in enumerate(urls)}
    postings = collections.defaultdict(list)

    for url in urls:
        counts = collections.Counter(w for w in words(texts[url]) if len(w) >= 3 and w not in STOP and not w.isdigit())

        for term, count in counts.items():
            postings[term].append(f"{position[url]}" if count == 1 else f"{position[url]}:{count}")

    lines = [
        "# Words said in each talk, from its English captions. Built by scripts/talk-keywords.py; do not edit.",
        "@talks " + " ".join(urls),
        "@unindexed " + " ".join(sorted(STOP)),
        *(f"{term} {' '.join(items)}" for term, items in sorted(postings.items())),
    ]
    open(path, "w").write("\n".join(lines) + "\n")
    print(f"said index: {len(postings)} words, {sum(len(items) for items in postings.values())} entries")


def main(talks_path, subs, out_path, said_path=None):
    talks = json.load(open(talks_path))
    by_video = collections.defaultdict(list)
    for talk in talks:
        vid, start = video_and_start(talk["youtube_url"])
        by_video[vid].append((start, talk))

    texts = {}
    for vid, items in by_video.items():
        path = caption_file(subs, vid)
        if not path:
            continue
        events = list(caption_events(path))
        items.sort(key=lambda item: item[0])
        for index, (start, talk) in enumerate(items):
            end = items[index + 1][0] if index + 1 < len(items) else float("inf")
            text = " ".join(t for s, t in events if start <= s < end)
            if len(text.split()) >= 200:
                texts[talk["youtube_url"]] = text

    per_talk = {url: candidates(words(text)) for url, text in texts.items()}
    df = collections.Counter(term for terms in per_talk.values() for term in terms)
    n = len(per_talk)

    out = {}
    for talk in talks:
        url = talk["youtube_url"]
        if url not in per_talk:
            continue
        known = set(words(" ".join([talk["title"], talk["summary"] or "", talk["speaker"], talk["conference"]])))
        scored = sorted(
            ((count * math.log(n / df[term]), term) for term, count in per_talk[url].items()),
            key=lambda pair: (-pair[0], pair[1]),
        )
        out[url] = [term for score, term in scored if term not in known][:TOP]

    # One talk per line, its words separated by spaces.
    lines = [f"    {json.dumps(url)}: {json.dumps(' '.join(terms))}" for url, terms in out.items()]
    open(out_path, "w").write("{\n" + ",\n".join(lines) + "\n}\n")
    print(f"talks with captions: {len(texts)} of {len(talks)}; keywords written for {len(out)}")

    if said_path:
        write_said(talks, texts, said_path)


if __name__ == "__main__":
    main(*sys.argv[1:5])
