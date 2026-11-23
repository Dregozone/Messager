# cloud-worker-setup

A Laravel project. Run the tests with `php artisan test --compact`.

<!-- cloud-worker:start (kept by Life OS: docs/office/cloud-worker/CLAUDE-section.md; edit it there) -->
# Working on an issue in the cloud

These rules apply when you're in a Claude Code **cloud session** (`CLAUDE_CODE_REMOTE` is `true`),
started from Life OS's "☁ Cloud worker" to work on a GitHub issue. The person reads your work on
their phone and may be away, so be thorough and don't stop for things you can decide yourself.

1. **Read the issue first.** Use your GitHub tools to read the issue named in the first message,
   with all its comments. If you can't read it, stop and say so: never guess from the title.
2. **Make a branch** from the default branch, named `issue-<number>-<short-title>` (for example
   `issue-17-birthday-reminders`). If pushing that name is refused, use the branch the session
   gives you; the pull request still links the issue.
3. **Do the work** the way this repository does things (the rest of this file, and its docs).
   The session's startup hook installed the dependencies; its line starts "Cloud setup (Life OS)".
   - If a step says FAILED, run the hook again once: `CLAUDE_CODE_REMOTE=true bash .claude/hooks/cloud-setup.sh`
     (its log is `/tmp/cloud-setup.log`).
   - **Spend at most 10 minutes on setup in all.** Don't invent your own install. If the tests
     still can't run, do the work anyway, and say plainly in the pull request (or, in a run of
     several issues, in a comment on the issue) that the tests weren't run, and why.
   - In every pull request, give the hook's line (it says how many seconds setup took), and say
     whether anything failed.
4. **Run the tests** (`php artisan test --compact`, plus `npm run build` if you changed the front
   end), and fix what you broke. Use SQLite for tests: there's no MySQL here.
5. **Bring the branch up to date** before the pull request: fetch, rebase onto the default branch,
   and run the tests again.
6. **Open the pull request yourself**, ready for review (not a draft):
   - Use your GitHub tools, or `gh api` (REST). `gh pr create` and `gh issue` don't work here
     (GitHub's GraphQL is blocked). For example:
     `gh api repos/OWNER/REPO/pulls -f title="…" -f head="issue-17-…" -f base="main" -f body="…"`
   - The description says what changed and why. Include an **Assumptions** section listing any
     choice you made without asking. End the description with `Closes #<number>`.
   - Where it helps the review (a visible change), add screenshots: commit them under
     `.github/pr-images/` on the branch and embed each with
     `![what it shows](https://github.com/OWNER/REPO/blob/COMMIT_SHA/.github/pr-images/NAME.png?raw=true)`.
7. **Check for overlap:** list the other open pull requests' files (`gh api repos/OWNER/REPO/pulls`
   and `…/pulls/N/files`). If any touch the same files, add a line to your description such as
   "Overlaps #12 in routes/web.php: either can merge first". Never push to another pull request's
   branch.
8. **Questions:** only ask when a real decision is needed that you can't sensibly make (the person
   answers from a phone). Otherwise choose, and list it under Assumptions.
9. **Never** print credentials, tokens or environment variables, and never commit `.env` or
   `auth.json`.

# Working through several issues in the cloud

When the first message lists several issues ("Work through these issues: #3, #5, #8"), the person
has asked for all of them in this one session, as **one pull request per group of related issues**.
They have sent it off and are not watching, so never stop to ask: everything above still applies to
each group, with these changes.

1. **Read every listed issue** and its comments. Leave out, and say why, any that is closed, that
   already has an open pull request (look for `Closes #N` in open pull requests' descriptions), or
   that you can't read.
2. **Group them:** issues go in one pull request when they change the same feature or the same
   files, at most 4 per pull request. Every other issue gets its own. Order the groups smallest and
   most independent first.
3. **Write the plan as your first message** (each group with its issues, the order, anything left
   out) and carry straight on. Don't wait for an answer.
4. **One branch per group, each from the up-to-date default branch** (`git fetch origin`, then
   `git switch -c issue-<first number>-<short-title> origin/<default>`), so every pull request
   stands alone and they can merge in any order. The person has asked for a separate branch and
   pull request for each group: push each group to its own branch, even if the session suggested a
   single working branch. Only if pushing a second branch is actually refused, stop starting new
   groups and say so in the summary.
5. **For each group**, follow steps 3–7 above. End its description with one `Closes #N` line per
   issue. Before the next group, go back to the default branch with a clean working tree: nothing
   from one group may end up in the next.
6. **When a group goes wrong** (tests you can't make pass, or a decision only the person can make):
   don't open a pull request for it and don't wait. Post a comment on each of its issues
   (`gh api repos/OWNER/REPO/issues/N/comments -f body="…"`) saying what blocked you or what needs
   deciding, with the options you see. Then go on to the next group.
7. **Finish with a summary:** each pull request (number, link, the issues it closes), and each issue
   not done, with why.

Outside a cloud session (at a desk on the PC), Life OS gives its own instructions instead: follow
those.
<!-- cloud-worker:end -->
