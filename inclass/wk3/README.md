# Week 3 — Testing, three levels

| | |
|---|---|
| **Tue 9/8** | One small PHP app, tested three ways: PHPUnit on its classes, Playwright on its API, Playwright on its page. In between, you write a prompt that gets an AI to write a test — and check that the test is real. |
| **Fri 9/11** | Continuous integration. **Homework 1 due. Homework 2 assigned — you will test Grocy, a real PHP app that shipped with no tests.** Project descriptions. |

**One hand-in for the week: `ANSWERS.md`, two short questions, filled in and pushed
before you leave class on Tuesday.** It counts toward participation.

## Get the answer sheet

Copy [`starter/ANSWERS.md`](starter/) into `inclass/wk3/` in **your own private repository**:

```bash
cp -R ../itws2110-course/inclass/wk3/starter/. inclass/wk3/
```

Copying the file across in Finder or Explorer works just as well.



---

## Tuesday — [`pantry/`](pantry/)



| | | |
|---|---|---|
| 0 | [Run the app, and see how it works](pantry/0-run-the-app.md) | `docker compose watch` · click around · the API · the four layers · SQLite · find the bug by hand |
| 1 | [Read the PHPUnit tests](pantry/1-phpunit.md) | a test is a sentence · arrange, act, assert · read a failure · data providers |
| 2 | [Write a prompt for the test you want](pantry/2-prompt.md) | one rule has no test · the prompt is the spec · red before green, or it is not a test |
| 3 | [API tests](pantry/3-api.md) | the same rule over HTTP · status codes and bodies · every test makes its own data |
| 4 | [Browser tests with Playwright](pantry/4-playwright.md) | the same rule as a person sees it · locators · what only a browser can catch |

Start with [`pantry/README.md`](pantry/README.md) — it explains the one image and the
three commands you will use all semester.

## Submit

Commit to `main` and push. No branch, no pull request.

```bash
git add inclass/wk3/
git commit -m "In-class week 3"
git push
```

Your answers go in `inclass/wk3/ANSWERS.md` **in your repository**, pushed before you leave.
Question 1 is what you noticed in Part 0; question 2 is your Part 2 prompt and what came back.
Keep both open as you work — they are written during class, not after.
